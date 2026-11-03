<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterielRequest;
use App\Http\Requests\UpdateMaterielAvailabilityRequest;
use App\Http\Requests\UpdateMaterielRequest;
use App\Http\Resources\MaterielResource;
use App\Models\Location;
use App\Models\Materiel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class MaterielController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            MaterielResource::collection(
                $request->user()->materiels()->withCount('locations')->get(),
            )->resolve($request),
        );
    }

    public function store(StoreMaterielRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $attributes = Arr::except($validated, ['photo']);

        if (isset($validated['photo'])) {
            $attributes['photo'] = $this->storePhoto($validated['photo']);
        }

        $materiel = $request->user()->materiels()->create($attributes);

        return response()->json(MaterielResource::make($materiel)->resolve($request), 201);
    }

    public function update(UpdateMaterielRequest $request, Materiel $materiel): JsonResponse
    {
        $validated = $request->validated();

        $oldPhoto = $materiel->photo;
        $attributes = Arr::except($validated, ['photo']);

        if (isset($validated['photo'])) {
            $attributes['photo'] = $this->storePhoto($validated['photo']);
        }

        $materiel->update($attributes);

        if (isset($attributes['photo']) && $oldPhoto !== null) {
            $this->deletePhoto($oldPhoto);
        }

        return response()->json(MaterielResource::make($materiel->refresh())->resolve($request));
    }

    public function destroy(Request $request, Materiel $materiel): Response
    {
        $this->ensureOwner($request, $materiel);

        $hasActiveOrUpcomingLocation = $materiel->locations()
            ->whereIn('statut', [Location::STATUT_EN_ATTENTE, Location::STATUT_CONFIRMEE])
            ->whereDate('date_fin', '>=', today())
            ->exists();

        if ($hasActiveOrUpcomingLocation) {
            return response()->json([
                'message' => 'Ce matériel ne peut pas être supprimé car il possède une location en cours ou à venir.',
                'errors' => (object) [],
            ], 422);
        }

        if ($materiel->locations()->exists()) {
            return response()->json([
                'message' => 'Ce matériel ne peut pas être supprimé car il possède un historique de locations.',
                'errors' => (object) [],
            ], 422);
        }

        $photo = $materiel->photo;
        $materiel->delete();

        $this->deletePhoto($photo);

        return response()->noContent();
    }

    public function updateAvailability(
        UpdateMaterielAvailabilityRequest $request,
        Materiel $materiel,
    ): JsonResponse {
        $validated = $request->validated();

        $materiel->update($validated);

        return response()->json(MaterielResource::make($materiel->refresh())->resolve($request));
    }

    private function ensureOwner(Request $request, Materiel $materiel): void
    {
        abort_unless(
            $materiel->proprietaire_id === $request->user()->getAuthIdentifier(),
            403,
            'Vous n’êtes pas propriétaire de ce matériel.',
        );
    }

    private function storePhoto(UploadedFile $photo): string
    {
        $path = $photo->store('materiels', 'public');

        if ($path === false) {
            throw new RuntimeException('Impossible d’enregistrer la photo du matériel.');
        }

        return $path;
    }

    private function deletePhoto(?string $path): void
    {
        $disk = Storage::disk('public');

        if ($path !== null && $disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('Impossible de supprimer la photo du matériel.');
        }
    }
}
