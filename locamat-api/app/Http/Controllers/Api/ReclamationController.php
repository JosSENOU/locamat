<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReclamationRequest;
use App\Http\Requests\UpdateReclamationRequest;
use App\Http\Resources\ReclamationResource;
use App\Models\AdminAction;
use App\Models\Location;
use App\Models\Reclamation;
use App\Models\User;
use App\Services\JournalAdministration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReclamationController extends Controller
{
    public function __construct(private readonly JournalAdministration $journal) {}

    public function store(StoreReclamationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $locataire = $request->user();
        $location = Location::query()
            ->with('materiel.proprietaire.roles')
            ->where('user_id', $locataire->getKey())
            ->findOrFail($validated['location_id']);
        $materiel = $location->materiel;
        $proprietaire = $materiel?->proprietaire;

        abort_unless(
            $proprietaire !== null
                && $proprietaire->getKey() !== $locataire->getKey()
                && $proprietaire->hasRole(User::ROLE_PROPRIETAIRE),
            422,
            'Le propriétaire de cette location ne peut pas être signalé.',
        );

        $reclamation = DB::transaction(function () use ($validated, $location, $locataire, $proprietaire): Reclamation {
            $reclamation = Reclamation::query()->create([
                'location_id' => $location->getKey(),
                'locataire_id' => $locataire->getKey(),
                'proprietaire_id' => $proprietaire->getKey(),
                'motif' => $validated['motif'],
                'description' => $validated['description'],
                'statut' => Reclamation::STATUT_NOUVELLE,
            ]);

            $this->journal->enregistrer(
                $locataire,
                AdminAction::RECLAMATION_CREEE,
                AdminAction::CIBLE_RECLAMATION,
                $reclamation->getKey(),
                'Signalement concernant la location #'.$location->getKey(),
                ['location_id' => $location->getKey(), 'motif' => $validated['motif']],
                $proprietaire->getKey(),
            );

            return $reclamation;
        });

        return ReclamationResource::make($reclamation->load([
            'location.materiel',
            'locataire',
            'proprietaire',
        ]))->response()->setStatusCode(201);
    }

    public function indexForLocataire(Request $request): JsonResponse
    {
        $reclamations = Reclamation::query()
            ->where('locataire_id', $request->user()->getKey())
            ->with(['location.materiel', 'locataire', 'proprietaire'])
            ->latest()
            ->get();

        return response()->json(ReclamationResource::collection($reclamations)->resolve($request));
    }

    public function indexForAdmin(Request $request): JsonResponse
    {
        $reclamations = Reclamation::query()
            ->with(['location.materiel', 'locataire', 'proprietaire'])
            ->latest()
            ->paginate(15);

        return ReclamationResource::collection($reclamations)->response();
    }

    public function update(UpdateReclamationRequest $request, Reclamation $reclamation): JsonResponse
    {
        $validated = $request->validated();
        $statutFinal = in_array($validated['statut'], [
            Reclamation::STATUT_TRAITEE,
            Reclamation::STATUT_REJETEE,
        ], true);

        DB::transaction(function () use ($request, $reclamation, $validated, $statutFinal): void {
            $reclamation = Reclamation::query()->lockForUpdate()->findOrFail($reclamation->getKey());
            $reclamation->update([
                'statut' => $validated['statut'],
                'note_admin' => array_key_exists('note_admin', $validated)
                    ? $validated['note_admin']
                    : $reclamation->note_admin,
                'traitee_at' => $statutFinal ? ($reclamation->traitee_at ?? now()) : null,
            ]);

            $this->journal->enregistrer(
                $request->user(),
                AdminAction::RECLAMATION_MISE_A_JOUR,
                AdminAction::CIBLE_RECLAMATION,
                $reclamation->getKey(),
                'Signalement concernant la location #'.$reclamation->location_id,
                ['statut' => $validated['statut']],
                $reclamation->locataire_id,
            );
        });

        return ReclamationResource::make($reclamation->refresh()->load([
            'location.materiel',
            'locataire',
            'proprietaire',
        ]))->response();
    }
}
