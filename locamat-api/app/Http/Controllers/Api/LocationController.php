<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProprietaireDemandesRequest;
use App\Http\Requests\RefuseLocationRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function demandes(ProprietaireDemandesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->getKey();

        $demandes = Location::query()
            ->with(['materiel', 'locataire'])
            ->whereHas('materiel', fn ($query) => $query->where('proprietaire_id', $userId))
            ->when(
                $validated['statut'] ?? null,
                fn ($query, $statut) => $query->where('statut', $statut),
            )
            ->latest()
            ->paginate(10);

        return LocationResource::collection($demandes)->response();
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $locationsDuProprietaire = Location::query()
            ->whereHas('materiel', fn ($query) => $query->where('proprietaire_id', $userId));
        $aujourdHui = today();
        $debutDuMois = now()->startOfMonth();
        $debutDuMoisProchain = now()->addMonthNoOverflow()->startOfMonth();

        $demandesEnAttente = (clone $locationsDuProprietaire)
            ->where('statut', Location::STATUT_EN_ATTENTE)
            ->count();
        $locationsEnCours = (clone $locationsDuProprietaire)
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->whereDate('date_debut', '<=', $aujourdHui)
            ->whereDate('date_fin', '>=', $aujourdHui)
            ->count();
        $revenusDuMois = (clone $locationsDuProprietaire)
            ->where('statut', Location::STATUT_TERMINEE)
            ->where('terminee_at', '>=', $debutDuMois)
            ->where('terminee_at', '<', $debutDuMoisProchain)
            ->sum('prix_total');

        return response()->json([
            'demandes_en_attente' => $demandesEnAttente,
            'locations_en_cours' => $locationsEnCours,
            'revenus_mois' => number_format((float) $revenusDuMois, 2, '.', ''),
        ]);
    }

    public function indexForProprietaire(Request $request): JsonResponse
    {
        $materielIds = $request->user()->materiels()->select('id');

        return response()->json(
            LocationResource::collection(Location::query()
                ->whereIn('materiel_id', $materielIds)
                ->with(['materiel:id,nom,categorie,proprietaire_id', 'locataire:id,name'])
                ->latest()
                ->get())->resolve($request),
        );
    }

    public function indexForLocataire(Request $request): JsonResponse
    {
        $locations = $request->user()->locations()
            ->with(['materiel:id,nom,categorie,proprietaire_id', 'materiel.proprietaire:id,name,telephone,photo,role'])
            ->latest()
            ->get();

        return response()->json(LocationResource::collection($locations)->resolve($request));
    }

    public function store(StoreLocationRequest $request, LocationService $locationService): JsonResponse
    {
        $location = $locationService->creerDemande($request->user(), $request->validated());

        return response()->json(LocationResource::make(
            $location->load(['materiel:id,nom,categorie,proprietaire_id', 'locataire:id,name']),
        )->resolve($request), 201);
    }

    public function confirmer(
        Request $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->confirmer($location, $request->user()),
        );
    }

    public function refuser(
        RefuseLocationRequest $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->refuser(
                $location,
                $request->user(),
                $request->validated()['motif_refus'] ?? null,
            ),
        );
    }

    public function terminer(
        Request $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->terminer($location, $request->user()),
        );
    }

    public function annuler(
        Request $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->annuler($location, $request->user()),
        );
    }

    private function respondWithLocation(Request $request, Location $location): JsonResponse
    {
        return response()->json(
            LocationResource::make(
                $location->load(['materiel:id,nom,categorie,proprietaire_id', 'locataire:id,name']),
            )->resolve($request),
        );
    }
}
