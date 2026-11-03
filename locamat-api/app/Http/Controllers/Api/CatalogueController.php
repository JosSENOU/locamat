<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogueIndexRequest;
use App\Http\Resources\MaterielResource;
use App\Models\Location;
use App\Models\Materiel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function index(CatalogueIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $materiels = Materiel::query()
            ->where('disponible', true)
            ->when($validated['q'] ?? null, function (Builder $query, string $recherche): void {
                $motCle = "%{$recherche}%";
                $query->where(function (Builder $query) use ($motCle): void {
                    $query->where('nom', 'like', $motCle)
                        ->orWhere('description', 'like', $motCle);
                });
            })
            ->when(
                $validated['categorie'] ?? null,
                fn (Builder $query, string $categorie) => $query->where('categorie', $categorie),
            )
            ->when(
                $validated['prix_min'] ?? null,
                fn (Builder $query, string|int|float $prixMin) => $query->where('prix_journalier', '>=', $prixMin),
            )
            ->when(
                $validated['prix_max'] ?? null,
                fn (Builder $query, string|int|float $prixMax) => $query->where('prix_journalier', '<=', $prixMax),
            )
            ->with(['proprietaire:id,name,photo,role'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return MaterielResource::collection($materiels)->response();
    }

    public function show(Request $request, Materiel $materiel): JsonResponse
    {
        return response()->json(MaterielResource::make(
            $materiel->load(['proprietaire:id,name,photo,role']),
        )->resolve($request));
    }

    public function periodesReservees(Materiel $materiel): JsonResponse
    {
        $periodes = $materiel->locations()
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->whereDate('date_fin', '>=', today())
            ->orderBy('date_debut')
            ->get(['date_debut', 'date_fin']);

        return response()->json($periodes);
    }
}
