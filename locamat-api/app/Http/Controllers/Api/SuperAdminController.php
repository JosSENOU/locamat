<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorbeilleIndexRequest;
use App\Http\Requests\JournalIndexRequest;
use App\Http\Requests\StoreAdministrateurRequest;
use App\Http\Requests\SuperAdminUtilisateursIndexRequest;
use App\Http\Resources\AdminActionResource;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\CorbeilleCategorieResource;
use App\Http\Resources\CorbeilleMaterielResource;
use App\Http\Resources\CorbeilleUtilisateurResource;
use App\Http\Resources\UtilisateurHistoriqueResource;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Materiel;
use App\Models\User;
use App\Services\CorbeilleService;
use App\Services\JournalAdministration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Espace du super-administrateur : il consulte l'historique de tout ce qui se passe sur la
 * plateforme, utilisateur par utilisateur, et restaure ce qu'un administrateur a supprimé par erreur.
 * Il n'a aucun tableau de bord comptable.
 */
class SuperAdminController extends Controller
{
    public function __construct(
        private readonly JournalAdministration $journal,
        private readonly CorbeilleService $corbeille,
    ) {}

    public function apercu(): JsonResponse
    {
        return response()->json([
            'administrateurs' => User::query()
                ->whereHas('roles', fn (Builder $query) => $query->where('name', User::ROLE_ADMIN))
                ->count(),
            'corbeille' => [
                'utilisateurs' => User::onlyTrashed()->whereNull('anonymise_at')->count(),
                'materiels' => Materiel::onlyTrashed()->count(),
                'categories' => Category::onlyTrashed()->count(),
            ],
            'retention_days' => (int) config('corbeille.retention_days'),
        ]);
    }

    /**
     * Journal de la plateforme. Le filtre `utilisateur` retient tout ce qu'un compte a fait ou subi :
     * ses propres actions, celles qui le concernent (propriétaire d'une location demandée) et celles
     * dont il est la cible (suppression, restauration, déblocage...).
     */
    public function journal(JournalIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $actions = AdminAction::query()
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('acteur_nom', 'like', "%{$search}%")
                        ->orWhere('cible_libelle', 'like', "%{$search}%");
                });
            })
            ->when($validated['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($validated['utilisateur'] ?? null, function (Builder $query, int|string $utilisateur): void {
                $query->where(function (Builder $query) use ($utilisateur): void {
                    $query->where('user_id', $utilisateur)
                        ->orWhere('concerne_user_id', $utilisateur)
                        ->orWhere(function (Builder $cible) use ($utilisateur): void {
                            $cible->where('cible_type', AdminAction::CIBLE_UTILISATEUR)
                                ->where('cible_id', $utilisateur);
                        });
                });
            })
            ->when($validated['du'] ?? null, fn (Builder $query, string $du) => $query->whereDate('created_at', '>=', $du))
            ->when($validated['au'] ?? null, fn (Builder $query, string $au) => $query->whereDate('created_at', '<=', $au))
            ->orderByDesc('id')
            ->paginate(20);

        return AdminActionResource::collection($actions)->response();
    }

    /**
     * Tous les comptes (supprimés compris) avec le nombre d'événements de leur historique.
     */
    public function utilisateurs(SuperAdminUtilisateursIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $evenementsDuCompte = fn (Builder $query) => $query->where(function (Builder $query): void {
            $query->whereColumn('admin_actions.user_id', 'users.id')
                ->orWhereColumn('admin_actions.concerne_user_id', 'users.id')
                ->orWhere(function (Builder $cible): void {
                    $cible->where('admin_actions.cible_type', AdminAction::CIBLE_UTILISATEUR)
                        ->whereColumn('admin_actions.cible_id', 'users.id');
                });
        });

        $users = User::query()
            ->withTrashed()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', User::ROLE_SUPER_ADMIN))
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                });
            })
            ->when($validated['role'] ?? null, fn (Builder $query, string $role) => $query->whereHas(
                'roles',
                fn (Builder $roleQuery) => $roleQuery->where('name', $role),
            ))
            ->addSelect([
                'actions_count' => $evenementsDuCompte(AdminAction::query()->selectRaw('count(*)')),
                'derniere_activite' => $evenementsDuCompte(AdminAction::query()->selectRaw('max(admin_actions.created_at)')),
            ])
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        return UtilisateurHistoriqueResource::collection($users)->response();
    }

    public function administrateurs(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $administrateurs = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', User::ROLE_ADMIN))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                });
            })
            ->with('roles')
            ->latest()
            ->paginate(15);

        return AdminUserResource::collection($administrateurs)->response();
    }

    public function storeAdministrateur(StoreAdministrateurRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $admin = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'telephone' => $validated['telephone'] ?? null,
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->syncRoles([User::ROLE_ADMIN]);

        $this->journal->enregistrer(
            $request->user(),
            AdminAction::ADMIN_CREE,
            AdminAction::CIBLE_UTILISATEUR,
            $admin->getKey(),
            $admin->name,
        );

        return AdminUserResource::make($admin->load('roles'))->response()->setStatusCode(201);
    }

    public function corbeille(CorbeilleIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'] ?? CorbeilleIndexRequest::TYPE_UTILISATEURS;
        $search = $validated['q'] ?? null;

        [$requete, $resource, $actionSuppression, $cibleType] = match ($type) {
            CorbeilleIndexRequest::TYPE_MATERIELS => [
                Materiel::onlyTrashed()
                    ->when($search, fn (Builder $query, string $terme) => $query->where('nom', 'like', "%{$terme}%"))
                    ->with(['photos', 'proprietaire'])
                    ->withCount('locations'),
                CorbeilleMaterielResource::class,
                AdminAction::MATERIEL_SUPPRIME,
                AdminAction::CIBLE_MATERIEL,
            ],
            CorbeilleIndexRequest::TYPE_CATEGORIES => [
                Category::onlyTrashed()
                    ->when($search, fn (Builder $query, string $terme) => $query->where('name', 'like', "%{$terme}%")),
                CorbeilleCategorieResource::class,
                AdminAction::CATEGORIE_SUPPRIMEE,
                AdminAction::CIBLE_CATEGORIE,
            ],
            default => [
                User::onlyTrashed()
                    ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', User::ROLE_SUPER_ADMIN))
                    ->when($search, function (Builder $query, string $terme): void {
                        $query->where(function (Builder $query) use ($terme): void {
                            $query->where('name', 'like', "%{$terme}%")
                                ->orWhere('email', 'like', "%{$terme}%");
                        });
                    })
                    ->with('roles')
                    ->withCount(['locations', 'materiels' => fn (Builder $query) => $query->withTrashed()]),
                CorbeilleUtilisateurResource::class,
                AdminAction::COMPTE_SUPPRIME,
                AdminAction::CIBLE_UTILISATEUR,
            ],
        };

        $elements = $requete->orderByDesc('deleted_at')->orderByDesc('id')->paginate(15);

        $this->joindreAuteurSuppression($elements->getCollection(), $actionSuppression, $cibleType);

        return $resource::collection($elements)
            ->additional(['retention_days' => (int) config('corbeille.retention_days')])
            ->response();
    }

    public function restaurer(Request $request, string $type, int $id): JsonResponse
    {
        $acteur = $request->user();

        match ($type) {
            CorbeilleIndexRequest::TYPE_UTILISATEURS => $this->corbeille->restaurerUtilisateur(
                User::onlyTrashed()->findOrFail($id),
                $acteur,
            ),
            CorbeilleIndexRequest::TYPE_MATERIELS => $this->corbeille->restaurerMateriel(
                Materiel::onlyTrashed()->findOrFail($id),
                $acteur,
            ),
            CorbeilleIndexRequest::TYPE_CATEGORIES => $this->corbeille->restaurerCategorie(
                Category::onlyTrashed()->findOrFail($id),
                $acteur,
            ),
        };

        return response()->json(['message' => 'L’élément a été restauré.']);
    }

    public function purger(Request $request, string $type, int $id): JsonResponse
    {
        $acteur = $request->user();

        $resultat = match ($type) {
            CorbeilleIndexRequest::TYPE_UTILISATEURS => $this->corbeille->purgerUtilisateur(
                User::onlyTrashed()->findOrFail($id),
                $acteur,
            ),
            CorbeilleIndexRequest::TYPE_MATERIELS => $this->corbeille->purgerMateriel(
                Materiel::onlyTrashed()->findOrFail($id),
                $acteur,
            ),
            CorbeilleIndexRequest::TYPE_CATEGORIES => (function () use ($id, $acteur): string {
                $this->corbeille->purgerCategorie(Category::onlyTrashed()->findOrFail($id), $acteur);

                return CorbeilleService::PURGE_SUPPRIME;
            })(),
        };

        $message = match ($resultat) {
            CorbeilleService::PURGE_ANONYMISE => 'Le compte a été anonymisé : ses données personnelles sont effacées, son historique est conservé.',
            CorbeilleService::PURGE_CONSERVE => 'Ce matériel est lié à des locations : il reste dans la corbeille pour garder l’historique.',
            default => 'L’élément a été supprimé définitivement.',
        };

        return response()->json(['message' => $message, 'resultat' => $resultat]);
    }

    /**
     * Ajoute à chaque élément de la corbeille la personne qui l'a supprimé, d'après le journal.
     *
     * @param  Collection<int, Model>  $elements
     */
    private function joindreAuteurSuppression(Collection $elements, string $action, string $cibleType): void
    {
        $derniereSuppression = AdminAction::query()
            ->where('action', $action)
            ->where('cible_type', $cibleType)
            ->whereIn('cible_id', $elements->modelKeys())
            ->orderBy('id')
            ->get()
            ->keyBy('cible_id');

        foreach ($elements as $element) {
            $suppression = $derniereSuppression->get($element->getKey());

            $element->setAttribute('supprime_par', $suppression === null ? null : [
                'nom' => $suppression->acteur_nom,
                'role' => $suppression->acteur_role,
                'le' => $suppression->created_at,
            ]);
        }
    }
}
