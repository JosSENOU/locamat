<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLocationsIndexRequest;
use App\Http\Requests\AdminMaterielsIndexRequest;
use App\Http\Requests\AdminUpdateMaterielAvailabilityRequest;
use App\Http\Requests\AdminUpdateUserRoleRequest;
use App\Http\Requests\AdminUpdateUserStatusRequest;
use App\Http\Requests\AdminUsersIndexRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\LocationResource;
use App\Http\Resources\MaterielResource;
use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $utilisateurs = User::query();
        $locations = Location::query();

        return response()->json([
            'total_utilisateurs' => (clone $utilisateurs)->count(),
            'utilisateurs_actifs' => (clone $utilisateurs)->where('is_active', true)->count(),
            'utilisateurs_suspendus' => (clone $utilisateurs)->where('is_active', false)->count(),
            'locataires' => (clone $utilisateurs)->where('role', User::ROLE_LOCATAIRE)->count(),
            'proprietaires' => (clone $utilisateurs)->where('role', User::ROLE_PROPRIETAIRE)->count(),
            'administrateurs' => (clone $utilisateurs)->where('role', User::ROLE_ADMIN)->count(),
            'total_materiels' => Materiel::query()->count(),
            'materiels_disponibles' => Materiel::query()->where('disponible', true)->count(),
            'locations_total' => (clone $locations)->count(),
            'demandes_en_attente' => (clone $locations)->where('statut', Location::STATUT_EN_ATTENTE)->count(),
            'locations_confirmees' => (clone $locations)->where('statut', Location::STATUT_CONFIRMEE)->count(),
            'locations_terminees' => (clone $locations)->where('statut', Location::STATUT_TERMINEE)->count(),
            'revenus_total' => number_format(
                (float) (clone $locations)->where('statut', Location::STATUT_TERMINEE)->sum('prix_total'),
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
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                });
            })
            ->when($validated['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when(
                array_key_exists('is_active', $validated),
                fn (Builder $query) => $query->where('is_active', $validated['is_active'] === '1'),
            )
            ->withCount('materiels')
            ->latest()
            ->paginate(15);

        return AdminUserResource::collection($users)->response();
    }

    public function updateRole(
        AdminUpdateUserRoleRequest $request,
        User $user,
    ): JsonResponse {
        $actor = $request->user();
        $role = $request->validated('role');

        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'role' => 'Vous ne pouvez pas modifier votre propre rôle.',
            ]);
        }

        $oldPhoto = $user->photo;

        DB::transaction(function () use ($user, $role): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->role === User::ROLE_ADMIN && $role !== User::ROLE_ADMIN) {
                $adminsActifs = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($target->is_active && $adminsActifs->count() <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Le dernier administrateur actif ne peut pas être rétrogradé.',
                    ]);
                }
            }

            $attributes = ['role' => $role];

            if ($role === User::ROLE_ADMIN) {
                $attributes['photo'] = null;
            }

            $target->update($attributes);
        });

        if ($role === User::ROLE_ADMIN && $oldPhoto !== null) {
            $disk = Storage::disk('public');

            if ($disk->exists($oldPhoto) && ! $disk->delete($oldPhoto)) {
                throw new RuntimeException('Impossible de supprimer la photo de profil de ce compte administrateur.');
            }
        }

        return AdminUserResource::make($user->refresh())->response();
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

        DB::transaction(function () use ($user, $isActive): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->role === User::ROLE_ADMIN && $target->is_active && ! $isActive) {
                $adminsActifs = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($adminsActifs->count() <= 1) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Le dernier administrateur actif ne peut pas être désactivé.',
                    ]);
                }
            }

            $target->update(['is_active' => $isActive]);

            if (! $isActive) {
                $target->tokens()->delete();
            }
        });

        return AdminUserResource::make($user->refresh())->response();
    }

    public function materiels(AdminMaterielsIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $materiels = Materiel::query()
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhere('categorie', 'like', "%{$search}%");
                });
            })
            ->when(
                array_key_exists('disponible', $validated),
                fn (Builder $query) => $query->where('disponible', $validated['disponible'] === '1'),
            )
            ->with(['proprietaire:id,name,photo,role'])
            ->withCount('locations')
            ->latest()
            ->paginate(15);

        return MaterielResource::collection($materiels)->response();
    }

    public function updateAvailability(
        AdminUpdateMaterielAvailabilityRequest $request,
        Materiel $materiel,
    ): JsonResponse {
        $materiel->update($request->validated());

        return MaterielResource::make(
            $materiel->refresh()->load(['proprietaire:id,name,photo,role'])->loadCount('locations'),
        )->response();
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
                'materiel.proprietaire:id,name,telephone,photo,role',
                'locataire:id,name,email,role,telephone',
            ])
            ->latest()
            ->paginate(15);

        return LocationResource::collection($locations)->response();
    }
}
