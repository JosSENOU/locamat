<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProprietaireDemandesRequest;
use App\Http\Requests\RefuseLocationRequest;
use App\Http\Requests\RequestLocationExtensionRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function demandes(ProprietaireDemandesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->getKey();

        $demandes = Location::query()
            ->with([
                'materiel',
                'materiel.categorie:id,name',
                'materiel.photos',
                'locataire',
                'locataire.roles:id,name',
                'historiques.acteur:id,name',
            ])
            ->whereHas('materiel', fn ($query) => $query->where('user_id', $userId))
            ->when(
                $validated['materiel_id'] ?? null,
                fn ($query, $materielId) => $query->where('materiel_id', $materielId),
            )
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
            ->whereHas('materiel', fn ($query) => $query->where('user_id', $userId));
        $aujourdHui = today();
        $debutDuMois = now()->startOfMonth();
        $debutDuMoisProchain = now()->addMonthNoOverflow()->startOfMonth();

        $demandesEnAttente = (clone $locationsDuProprietaire)
            ->where('statut', Location::STATUT_EN_ATTENTE)
            ->count();
        $locationsEnCours = (clone $locationsDuProprietaire)
            ->consommeStockDepuis($aujourdHui->toDateString())
            ->count();
        $revenusDuMois = (clone $locationsDuProprietaire)
            ->where('statut', Location::STATUT_TERMINEE)
            ->where('terminee_at', '>=', $debutDuMois)
            ->where('terminee_at', '<', $debutDuMoisProchain)
            ->sum(DB::raw('prix_total + COALESCE(penalite_montant, 0)'));

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
                ->with(['materiel:id,nom,categorie_id,user_id,prix_journalier', 'materiel.categorie:id,name', 'materiel.photos', 'locataire:id,name', 'historiques.acteur:id,name'])
                ->latest()
                ->get())->resolve($request),
        );
    }

    public function indexForLocataire(Request $request): JsonResponse
    {
        $locations = $request->user()->locations()
            ->with([
                'materiel:id,nom,categorie_id,user_id,prix_journalier',
                'materiel.categorie:id,name',
                'materiel.photos',
                'materiel.proprietaire:id,name,telephone,photo,ville,quartier,adresse',
                'materiel.proprietaire.roles:id,name',
                'historiques.acteur:id,name',
            ])
            ->latest()
            ->get();

        return response()->json(LocationResource::collection($locations)->resolve($request));
    }

    public function store(StoreLocationRequest $request, LocationService $locationService): JsonResponse
    {
        $location = $locationService->creerDemande($request->user(), $request->validated());

        return response()->json(LocationResource::make(
            $location->load([
                'materiel:id,nom,categorie_id,user_id,prix_journalier',
                'materiel.categorie:id,name',
                'materiel.photos',
                'locataire:id,name',
                'historiques.acteur:id,name',
            ]),
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

    public function demanderProlongation(
        RequestLocationExtensionRequest $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->demanderProlongation(
                $location,
                $request->user(),
                $request->validated()['date_fin'],
            ),
        );
    }

    public function confirmerProlongation(
        Request $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->confirmerProlongation($location, $request->user()),
        );
    }

    public function refuserProlongation(
        Request $request,
        Location $location,
        LocationService $locationService,
    ): JsonResponse {
        return $this->respondWithLocation(
            $request,
            $locationService->refuserProlongation($location, $request->user()),
        );
    }

    private function respondWithLocation(Request $request, Location $location): JsonResponse
    {
        return response()->json(
            LocationResource::make(
                $location->load([
                    'materiel:id,nom,categorie_id,user_id,prix_journalier',
                    'materiel.categorie:id,name',
                    'materiel.photos',
                    'locataire:id,name',
                    'historiques.acteur:id,name',
                ]),
            )->resolve($request),
        );
    }
}
