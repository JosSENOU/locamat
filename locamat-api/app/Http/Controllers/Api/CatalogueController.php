<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogueIndexRequest;
use App\Http\Resources\MaterielResource;
use App\Models\Category;
use App\Models\Location;
use App\Models\Materiel;
use App\Services\LocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(Category::query()->orderBy('name')->get(['id', 'name']));
    }

    public function index(CatalogueIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $materiels = Materiel::query()
            ->where('disponible', true)
            ->whereRaw(
                '(select coalesce(sum(locations.quantite), 0) from locations where locations.materiel_id = materiels.id and locations.statut = ?) < materiels.quantite',
                [Location::STATUT_CONFIRMEE],
            )
            ->when($validated['q'] ?? null, function (Builder $query, string $recherche): void {
                $motCle = "%{$recherche}%";
                $query->where(function (Builder $query) use ($motCle): void {
                    $query->where('nom', 'like', $motCle)
                        ->orWhere('description', 'like', $motCle);
                });
            })
            ->when(
                $validated['categorie_id'] ?? null,
                fn (Builder $query, int $categorieId) => $query->where('categorie_id', $categorieId),
            )
            ->when($validated['categorie'] ?? null, function (Builder $query, string $categorie): void {
                $query->whereHas('categorie', fn (Builder $categoryQuery) => $categoryQuery->where('name', $categorie));
            })
            ->when(
                $validated['prix_min'] ?? null,
                fn (Builder $query, string|int|float $prixMin) => $query->where('prix_journalier', '>=', $prixMin),
            )
            ->when(
                $validated['prix_max'] ?? null,
                fn (Builder $query, string|int|float $prixMax) => $query->where('prix_journalier', '<=', $prixMax),
            )
            ->with(['categorie:id,name', 'photos', 'proprietaire:id,name,photo,ville,quartier', 'proprietaire.roles:id,name'])
            ->withCount(['locations as locations_count' => fn (Builder $query) => $query
                ->where('statut', Location::STATUT_TERMINEE)])
            ->withSum(['locations as locations_reservees_count' => fn (Builder $query) => $query
                ->reserveStock()], 'quantite')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return MaterielResource::collection($materiels)->response();
    }

    public function show(Request $request, Materiel $materiel): JsonResponse
    {
        return response()->json(MaterielResource::make(
            $materiel->load([
                'categorie:id,name',
                'photos',
                'proprietaire:id,name,photo,ville,quartier',
                'proprietaire.roles:id,name',
            ])->loadCount(['locations as locations_count' => fn (Builder $query) => $query
                ->where('statut', Location::STATUT_TERMINEE)])
                ->loadSum(['locations as locations_reservees_count' => fn (Builder $query) => $query
                    ->reserveStock()], 'quantite'),
        )->resolve($request));
    }

    public function periodesReservees(Materiel $materiel, LocationService $locationService): JsonResponse
    {
        return response()->json($locationService->periodesIndisponibles($materiel));
    }
}
