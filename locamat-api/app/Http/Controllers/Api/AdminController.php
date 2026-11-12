<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLocationsIndexRequest;
use App\Http\Requests\AdminMaterielsIndexRequest;
use App\Http\Requests\AdminUpdateMaterielAvailabilityRequest;
use App\Http\Requests\AdminUpdateUserProfileRequest;
use App\Http\Requests\AdminUpdateUserStatusRequest;
use App\Http\Requests\AdminUsersIndexRequest;
use App\Http\Requests\SaveCategoryRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\LocationResource;
use App\Http\Resources\MaterielResource;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use App\Services\CorbeilleService;
use App\Services\JournalAdministration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private readonly JournalAdministration $journal,
        private readonly CorbeilleService $corbeille,
    ) {}

    public function categories(): JsonResponse
    {
        return response()->json(
            Category::query()->withCount('materiels')->orderBy('name')->get(),
        );
    }

    public function storeCategory(SaveCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($request->validated());

        $this->journal->enregistrer(
            $request->user(),
            AdminAction::CATEGORIE_CREEE,
            AdminAction::CIBLE_CATEGORIE,
            $category->getKey(),
            $category->name,
        );

        return response()->json($category->loadCount('materiels'), 201);
    }

    public function updateCategory(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $ancienNom = $category->name;
        $category->update($request->validated());

        $this->journal->enregistrer(
            $request->user(),
            AdminAction::CATEGORIE_MODIFIEE,
            AdminAction::CIBLE_CATEGORIE,
            $category->getKey(),
            $category->name,
            ['ancien_nom' => $ancienNom],
        );

        return response()->json($category->refresh()->loadCount('materiels'));
    }

    public function destroyCategory(Request $request, Category $category): JsonResponse
    {
        $this->corbeille->supprimerCategorie($category, $request->user());

        return response()->json(['message' => 'La catégorie a été placée dans la corbeille.']);
    }

    public function stats(): JsonResponse
    {
        $utilisateurs = User::query()->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', User::ROLE_SUPER_ADMIN));
        $locations = Location::query();

        return response()->json([
            'total_utilisateurs' => (clone $utilisateurs)->count(),
            'utilisateurs_actifs' => (clone $utilisateurs)->where('is_active', true)->count(),
            'utilisateurs_suspendus' => (clone $utilisateurs)->where('is_active', false)->count(),
            'locataires' => (clone $utilisateurs)->whereHas('roles', fn (Builder $query) => $query->where('name', User::ROLE_LOCATAIRE))->count(),
            'proprietaires' => (clone $utilisateurs)->whereHas('roles', fn (Builder $query) => $query->where('name', User::ROLE_PROPRIETAIRE))->count(),
            'administrateurs' => (clone $utilisateurs)->whereHas('roles', fn (Builder $query) => $query->where('name', User::ROLE_ADMIN))->count(),
            'total_materiels' => Materiel::query()->count(),
            'materiels_disponibles' => Materiel::query()->where('disponible', true)->count(),
            'locations_total' => (clone $locations)->count(),
            'demandes_en_attente' => (clone $locations)->where('statut', Location::STATUT_EN_ATTENTE)->count(),
            'locations_confirmees' => (clone $locations)->where('statut', Location::STATUT_CONFIRMEE)->count(),
            'locations_terminees' => (clone $locations)->where('statut', Location::STATUT_TERMINEE)->count(),
            'revenus_total' => number_format(
                (float) (clone $locations)
                    ->where('statut', Location::STATUT_TERMINEE)
                    ->sum(DB::raw('prix_total + COALESCE(penalite_montant, 0)')),
                2,
                '.',
                '',
            ),
        ]);
    }

    public function utilisateurs(AdminUsersIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $users = User::query()
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
            ->when(
                array_key_exists('is_active', $validated),
                fn (Builder $query) => $query->where('is_active', $validated['is_active'] === '1'),
            )
            ->with('roles')
            ->withCount('materiels')
            ->latest()
            ->paginate(15);

        return AdminUserResource::collection($users)->response();
    }

    public function updateStatus(
        AdminUpdateUserStatusRequest $request,
        User $user,
    ): JsonResponse {
        $actor = $request->user();
        $isActive = $request->validated('is_active');

        if ($actor->is($user) && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $this->ensureCanManage($actor, $user);

        DB::transaction(function () use ($actor, $user, $isActive): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->isAdministrator() && $target->is_active && ! $isActive) {
                if (! $this->anotherActiveAdministratorRemains($target)) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Le dernier administrateur actif ne peut pas être désactivé.',
                    ]);
                }
            }

            $target->update(['is_active' => $isActive]);

            if (! $isActive) {
                $target->tokens()->delete();
            }

            $this->journal->enregistrer(
                $actor,
                AdminAction::STATUT_MODIFIE,
                AdminAction::CIBLE_UTILISATEUR,
                $target->getKey(),
                $target->name,
                ['is_active' => (bool) $isActive],
            );
        });

        return AdminUserResource::make($user->refresh()->load('roles'))->response();
    }

    public function unlockAccount(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $user);

        DB::transaction(function () use ($actor, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedUser->forceFill([
                'failed_login_attempts' => 0,
                'login_locked_at' => null,
            ])->save();

            $this->journal->enregistrer(
                $actor,
                AdminAction::COMPTE_DEBLOQUE,
                AdminAction::CIBLE_UTILISATEUR,
                $lockedUser->getKey(),
                $lockedUser->name,
            );
        });

        return AdminUserResource::make($user->refresh()->load('roles'))->response();
    }

    /**
     * Permet à l'administrateur de modifier le profil d'un compte déjà inscrit
     * (nom, téléphone) et, pour un propriétaire, sa localisation.
     */
    public function updateProfile(
        AdminUpdateUserProfileRequest $request,
        User $user,
    ): JsonResponse {
        $actor = $request->user();
        $this->ensureCanManage($actor, $user);

        $user->update($request->validated());

        $this->journal->enregistrer(
            $actor,
            AdminAction::PROFIL_MODIFIE,
            AdminAction::CIBLE_UTILISATEUR,
            $user->getKey(),
            $user->name,
            ['champs' => array_keys($request->validated())],
        );

        return AdminUserResource::make($user->refresh()->load('roles'))->response();
    }

    /**
     * Suppression logique : le compte et ses matériels vont dans la corbeille, l'historique des
     * locations est conservé et le super-administrateur peut le restaurer.
     */
    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        $this->ensureCanManage($actor, $user);

        if ($user->isAdministrator() && $user->is_active && ! $this->anotherActiveAdministratorRemains($user)) {
            throw ValidationException::withMessages([
                'user' => 'Le dernier administrateur actif ne peut pas être supprimé.',
            ]);
        }

        $this->corbeille->supprimerUtilisateur($user, $actor);

        return response()->json(['message' => 'Le compte a été placé dans la corbeille.']);
    }

    public function materiels(AdminMaterielsIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $materiels = Materiel::query()
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhereHas('categorie', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(
                array_key_exists('disponible', $validated),
                fn (Builder $query) => $query->where('disponible', $validated['disponible'] === '1'),
            )
            ->with(['categorie:id,name', 'photos', 'proprietaire:id,name,photo,ville,quartier', 'proprietaire.roles:id,name'])
            ->withCount(['locations as locations_count' => fn (Builder $query) => $query->where('statut', Location::STATUT_TERMINEE)])
            ->latest()
            ->paginate(15);

        return MaterielResource::collection($materiels)->response();
    }

    public function updateAvailability(
        AdminUpdateMaterielAvailabilityRequest $request,
        Materiel $materiel,
    ): JsonResponse {
        $materiel->update($request->validated());

        $this->journal->enregistrer(
            $request->user(),
            AdminAction::MATERIEL_DISPONIBILITE,
            AdminAction::CIBLE_MATERIEL,
            $materiel->getKey(),
            $materiel->nom,
            ['disponible' => (bool) $materiel->disponible],
        );

        return MaterielResource::make(
            $materiel->refresh()
                ->load(['categorie:id,name', 'photos', 'proprietaire:id,name,photo,ville,quartier', 'proprietaire.roles:id,name'])
                ->loadCount(['locations as locations_count' => fn (Builder $query) => $query->where('statut', Location::STATUT_TERMINEE)]),
        )->response();
    }

    public function destroyMateriel(Request $request, Materiel $materiel): JsonResponse
    {
        $this->corbeille->supprimerMateriel($materiel, $request->user());

        return response()->json(['message' => 'Le matériel a été placé dans la corbeille.']);
    }

    public function locations(AdminLocationsIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $locations = Location::query()
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('materiel', fn (Builder $materiel) => $materiel->where('nom', 'like', "%{$search}%"))
                        ->orWhereHas('locataire', function (Builder $locataire) use ($search): void {
                            $locataire->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($validated['statut'] ?? null, fn (Builder $query, string $statut) => $query->where('statut', $statut))
            ->with([
                'materiel.categorie:id,name',
                'materiel.photos',
                'materiel.proprietaire:id,name,telephone,photo,ville,quartier,adresse',
                'materiel.proprietaire.roles:id,name',
                'locataire:id,name,email,telephone',
                'locataire.roles:id,name',
            ])
            ->latest()
            ->paginate(15);

        return LocationResource::collection($locations)->response();
    }

    /**
     * Un administrateur gère les locataires et les propriétaires ; seuls les comptes
     * administrateurs relèvent du super-administrateur, dont le compte est intouchable ici.
     */
    private function ensureCanManage(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            return;
        }

        abort_if(
            $target->isSuperAdmin(),
            403,
            'Un compte super-administrateur ne peut pas être modifié depuis l’administration.',
        );
        abort_if(
            $target->hasRole(User::ROLE_ADMIN) && ! $actor->isSuperAdmin(),
            403,
            'Seul le super-administrateur peut gérer les comptes administrateurs.',
        );
    }

    /**
     * La plateforme doit toujours garder au moins un administrateur actif (admin ou super-admin).
     */
    private function anotherActiveAdministratorRemains(User $target): bool
    {
        return User::query()
            ->whereKeyNot($target->getKey())
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]))
            ->lockForUpdate()
            ->pluck('id')
            ->isNotEmpty();
    }
}
