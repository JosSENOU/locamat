<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Suppression logique, corbeille et purge des comptes et des matériels.
 *
 * Un élément supprimé reste en base (colonne `deleted_at`) pour conserver l'historique des
 * locations. Il peut être restauré jusqu'à la fin de la durée de conservation
 * (`corbeille.retention_days`), puis la purge le supprime définitivement ; un compte ou un matériel
 * rattaché à des locations est alors anonymisé ou conservé, jamais détruit : l'historique comptable
 * ne doit pas disparaître.
 */
class CorbeilleService
{
    public const PURGE_SUPPRIME = 'supprime';

    public const PURGE_ANONYMISE = 'anonymise';

    public const PURGE_CONSERVE = 'conserve';

    public function __construct(private readonly JournalAdministration $journal) {}

    public function supprimerUtilisateur(User $user, User $acteur): void
    {
        DB::transaction(function () use ($user, $acteur): void {
            $cible = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $locationEnCours = Location::query()
                ->whereIn('statut', [Location::STATUT_EN_ATTENTE, Location::STATUT_CONFIRMEE])
                ->where(function ($query) use ($cible): void {
                    $query->where('user_id', $cible->getKey())
                        ->orWhereHas('materiel', fn ($materiel) => $materiel->where('user_id', $cible->getKey()));
                })
                ->exists();

            abort_if(
                $locationEnCours,
                409,
                'Ce compte a des locations en attente ou en cours. Terminez-les ou annulez-les avant de le supprimer.',
            );

            // Même horodatage pour le compte et ses matériels : la restauration sait ainsi
            // lesquels ont été retirés avec lui.
            $horodatage = now()->startOfSecond();
            $cible->materiels()->update(['deleted_at' => $horodatage]);
            $cible->forceFill(['deleted_at' => $horodatage])->save();
            $cible->tokens()->delete();

            $this->journal->enregistrer(
                $acteur,
                AdminAction::COMPTE_SUPPRIME,
                AdminAction::CIBLE_UTILISATEUR,
                $cible->getKey(),
                $cible->name,
            );
        });
    }

    public function restaurerUtilisateur(User $user, User $acteur): void
    {
        abort_if(
            $user->anonymise_at !== null,
            409,
            'Ce compte a été anonymisé à la fin de la durée de conservation : il ne peut plus être restauré.',
        );

        DB::transaction(function () use ($user, $acteur): void {
            $supprimeLe = $user->deleted_at;

            Materiel::onlyTrashed()
                ->where('user_id', $user->getKey())
                ->where('deleted_at', $supprimeLe)
                ->update(['deleted_at' => null]);

            $user->restore();

            $this->journal->enregistrer(
                $acteur,
                AdminAction::COMPTE_RESTAURE,
                AdminAction::CIBLE_UTILISATEUR,
                $user->getKey(),
                $user->name,
            );
        });
    }

    public function supprimerMateriel(Materiel $materiel, User $acteur): void
    {
        DB::transaction(function () use ($materiel, $acteur): void {
            $locationEnCours = $materiel->locations()
                ->whereIn('statut', [Location::STATUT_EN_ATTENTE, Location::STATUT_CONFIRMEE])
                ->exists();

            abort_if(
                $locationEnCours,
                409,
                'Ce matériel a des locations en attente ou en cours. Terminez-les ou annulez-les avant de le supprimer.',
            );

            $materiel->delete();

            $this->journal->enregistrer(
                $acteur,
                AdminAction::MATERIEL_SUPPRIME,
                AdminAction::CIBLE_MATERIEL,
                $materiel->getKey(),
                $materiel->nom,
            );
        });
    }

    public function restaurerMateriel(Materiel $materiel, User $acteur): void
    {
        abort_if(
            $materiel->proprietaire?->trashed(),
            409,
            'Le propriétaire de ce matériel est dans la corbeille. Restaurez d’abord son compte.',
        );

        DB::transaction(function () use ($materiel, $acteur): void {
            $materiel->restore();

            $this->journal->enregistrer(
                $acteur,
                AdminAction::MATERIEL_RESTAURE,
                AdminAction::CIBLE_MATERIEL,
                $materiel->getKey(),
                $materiel->nom,
            );
        });
    }

    /**
     * Supprime définitivement un matériel de la corbeille, sauf s'il est rattaché à des locations :
     * il reste alors dans la corbeille pour que l'historique des achats garde son sens.
     *
     * @return self::PURGE_SUPPRIME|self::PURGE_CONSERVE
     */
    public function purgerMateriel(Materiel $materiel, ?User $acteur = null): string
    {
        abort_unless($materiel->trashed(), 409, 'Ce matériel n’est pas dans la corbeille.');

        if ($materiel->locations()->exists()) {
            return self::PURGE_CONSERVE;
        }

        $identifiant = $materiel->getKey();
        $nom = $materiel->nom;

        DB::transaction(function () use ($materiel): void {
            $chemins = $materiel->photos()->pluck('path')->all();

            $materiel->photos()->delete();
            $materiel->forceDelete();

            Storage::disk('public')->delete($chemins);
        });

        $this->journal->enregistrer($acteur, AdminAction::MATERIEL_PURGE, AdminAction::CIBLE_MATERIEL, $identifiant, $nom);

        return self::PURGE_SUPPRIME;
    }

    /**
     * Supprime définitivement un compte de la corbeille. Un compte rattaché à des locations (en tant
     * que locataire ou propriétaire) est anonymisé : ses données personnelles disparaissent, mais les
     * achats qui le référencent restent cohérents.
     *
     * @return self::PURGE_SUPPRIME|self::PURGE_ANONYMISE
     */
    public function purgerUtilisateur(User $user, ?User $acteur = null): string
    {
        abort_unless($user->trashed(), 409, 'Ce compte n’est pas dans la corbeille.');
        abort_if($user->anonymise_at !== null, 409, 'Ce compte est déjà anonymisé.');

        $identifiant = $user->getKey();
        $nom = $user->name;

        $resultat = DB::transaction(function () use ($user, $acteur): string {
            $user->materiels()->onlyTrashed()->get()
                ->each(fn (Materiel $materiel) => $this->purgerMateriel($materiel, $acteur));

            $photo = $user->photo;
            $user->tokens()->delete();
            $user->notifications()->delete();

            $aUnHistorique = $user->locations()->exists()
                || $user->materiels()->withTrashed()->exists();

            if ($aUnHistorique) {
                $user->forceFill([
                    'name' => 'Compte supprimé',
                    'email' => 'compte-supprime-'.$user->getKey().'@locamat.invalid',
                    'password' => Str::random(40),
                    'telephone' => null,
                    'ville' => null,
                    'quartier' => null,
                    'adresse' => null,
                    'photo' => null,
                    'is_active' => false,
                    'failed_login_attempts' => 0,
                    'login_locked_at' => null,
                    'remember_token' => null,
                    'anonymise_at' => now(),
                ])->save();
                $resultat = self::PURGE_ANONYMISE;
            } else {
                $user->roles()->detach();
                $user->forceDelete();
                $resultat = self::PURGE_SUPPRIME;
            }

            if ($photo !== null) {
                Storage::disk('public')->delete($photo);
            }

            return $resultat;
        });

        $this->journal->enregistrer(
            $acteur,
            AdminAction::COMPTE_PURGE,
            AdminAction::CIBLE_UTILISATEUR,
            $identifiant,
            $nom,
            ['resultat' => $resultat],
        );

        return $resultat;
    }

    public function supprimerCategorie(Category $category, User $acteur): void
    {
        abort_if(
            $category->materiels()->withTrashed()->exists(),
            409,
            'Cette catégorie est utilisée par un ou plusieurs matériels et ne peut pas être supprimée.',
        );

        DB::transaction(function () use ($category, $acteur): void {
            $category->delete();

            $this->journal->enregistrer(
                $acteur,
                AdminAction::CATEGORIE_SUPPRIMEE,
                AdminAction::CIBLE_CATEGORIE,
                $category->getKey(),
                $category->name,
            );
        });
    }

    public function restaurerCategorie(Category $category, User $acteur): void
    {
        DB::transaction(function () use ($category, $acteur): void {
            $category->restore();

            $this->journal->enregistrer(
                $acteur,
                AdminAction::CATEGORIE_RESTAUREE,
                AdminAction::CIBLE_CATEGORIE,
                $category->getKey(),
                $category->name,
            );
        });
    }

    /**
     * Supprime définitivement une catégorie de la corbeille. Elle n'a plus de matériel (c'est la
     * condition de sa suppression), donc aucun historique n'en dépend.
     */
    public function purgerCategorie(Category $category, ?User $acteur = null): void
    {
        abort_unless($category->trashed(), 409, 'Cette catégorie n’est pas dans la corbeille.');

        $identifiant = $category->getKey();
        $nom = $category->name;

        $category->forceDelete();

        $this->journal->enregistrer($acteur, AdminAction::CATEGORIE_PURGEE, AdminAction::CIBLE_CATEGORIE, $identifiant, $nom);
    }

    /**
     * Purge tout ce qui a dépassé la durée de conservation.
     *
     * @return array{utilisateurs_supprimes: int, utilisateurs_anonymises: int, materiels_supprimes: int, categories_supprimees: int}
     */
    public function purgerExpires(): array
    {
        $limite = now()->subDays(max(1, (int) config('corbeille.retention_days')));
        $resume = ['utilisateurs_supprimes' => 0, 'utilisateurs_anonymises' => 0, 'materiels_supprimes' => 0, 'categories_supprimees' => 0];

        User::onlyTrashed()
            ->whereNull('anonymise_at')
            ->where('deleted_at', '<=', $limite)
            ->lazyById(100)
            ->each(function (User $user) use (&$resume): void {
                $resultat = $this->purgerUtilisateur($user);
                $cle = $resultat === self::PURGE_ANONYMISE ? 'utilisateurs_anonymises' : 'utilisateurs_supprimes';
                $resume[$cle]++;
            });

        Materiel::onlyTrashed()
            ->where('deleted_at', '<=', $limite)
            ->whereDoesntHave('locations')
            ->lazyById(100)
            ->each(function (Materiel $materiel) use (&$resume): void {
                if ($this->purgerMateriel($materiel) === self::PURGE_SUPPRIME) {
                    $resume['materiels_supprimes']++;
                }
            });

        Category::onlyTrashed()
            ->where('deleted_at', '<=', $limite)
            ->lazyById(100)
            ->each(function (Category $category) use (&$resume): void {
                $this->purgerCategorie($category);
                $resume['categories_supprimees']++;
            });

        return $resume;
    }
}
