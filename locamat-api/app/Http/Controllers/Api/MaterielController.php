<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterielRequest;
use App\Http\Requests\UpdateMaterielAvailabilityRequest;
use App\Http\Requests\UpdateMaterielRequest;
use App\Http\Resources\MaterielResource;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Location;
use App\Models\Materiel;
use App\Services\JournalAdministration;
use App\Services\LocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MaterielController extends Controller
{
    public function __construct(private readonly JournalAdministration $journal) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            MaterielResource::collection(
                $request->user()->materiels()
                    ->with(['categorie', 'photos'])
                    ->withCount(['locations as locations_count' => fn (Builder $query) => $query->where('statut', Location::STATUT_TERMINEE)])
                    ->withSum(['locations as locations_reservees_count' => fn (Builder $query) => $query
                        ->reserveStock()], 'quantite')
                    ->get(),
            )->resolve($request),
        );
    }

    public function store(StoreMaterielRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $attributes = $this->materialAttributes($validated);
        $photos = $this->uploadedPhotos($validated);
        $photoPaths = $this->storePhotos($photos);

        try {
            $materiel = DB::transaction(function () use ($request, $attributes, $photoPaths): Materiel {
                $materiel = $request->user()->materiels()->create($attributes);
                $this->createPhotoRows($materiel, $photoPaths);

                return $materiel;
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($photoPaths);
            throw $exception;
        }

        $this->journaliser($request, AdminAction::MATERIEL_CREE, $materiel);

        return response()->json(
            MaterielResource::make($materiel->load(['categorie', 'photos']))->resolve($request),
            201,
        );
    }

    public function update(
        UpdateMaterielRequest $request,
        Materiel $materiel,
        LocationService $locationService,
    ): JsonResponse {
        $validated = $request->validated();

        $oldPhotoPaths = $materiel->photos()->pluck('path')->all();
        $attributes = $this->materialAttributes($validated);
        $replacePhotos = array_key_exists('photos', $validated);
        $photoPaths = $this->storePhotos($replacePhotos ? $this->uploadedPhotos($validated) : []);

        try {
            DB::transaction(function () use ($materiel, $attributes, $replacePhotos, $photoPaths, $locationService): void {
                $lockedMateriel = Materiel::query()->lockForUpdate()->findOrFail($materiel->getKey());
                if (
                    isset($attributes['quantite'])
                    && (int) $attributes['quantite'] < $locationService->nombreMaximumLocationsSimultanees(
                        (int) $lockedMateriel->getKey(),
                    )
                ) {
                    abort(422, 'Le stock ne peut pas être inférieur au nombre de locations simultanées déjà confirmées.');
                }

                $lockedMateriel->update($attributes);

                if ($replacePhotos) {
                    $lockedMateriel->photos()->delete();
                    $this->createPhotoRows($lockedMateriel, $photoPaths);
                }
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($photoPaths);
            throw $exception;
        }

        if ($replacePhotos) {
            $this->deletePhotos($oldPhotoPaths);
        }

        $this->journaliser($request, AdminAction::MATERIEL_MODIFIE, $materiel);

        return response()->json(
            MaterielResource::make($materiel->refresh()->load(['categorie', 'photos'])->loadSum([
                'locations as locations_reservees_count' => fn (Builder $query) => $query
                    ->reserveStock(),
            ], 'quantite'))->resolve($request),
        );
    }

    public function destroy(Request $request, Materiel $materiel): Response
    {
        $this->ensureOwner($request, $materiel);

        $hasActiveOrUpcomingLocation = $materiel->locations()
            ->whereIn('statut', [Location::STATUT_EN_ATTENTE, Location::STATUT_CONFIRMEE])
            ->where(function (Builder $query): void {
                $query->whereDate('date_fin', '>=', today())
                    ->orWhere(function (Builder $overdueQuery): void {
                        $overdueQuery
                            ->where('statut', Location::STATUT_CONFIRMEE)
                            ->whereDate('date_debut', '<=', today());
                    });
            })
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

        // Suppression logique : le matériel reste restaurable depuis la corbeille, ses photos aussi.
        $materiel->delete();

        $this->journaliser($request, AdminAction::MATERIEL_SUPPRIME, $materiel);

        return response()->noContent();
    }

    public function updateAvailability(
        UpdateMaterielAvailabilityRequest $request,
        Materiel $materiel,
    ): JsonResponse {
        $validated = $request->validated();

        $materiel->update($validated);

        $this->journaliser($request, AdminAction::MATERIEL_DISPONIBILITE, $materiel, ['disponible' => (bool) $materiel->disponible]);

        return response()->json(
            MaterielResource::make($materiel->refresh()->load(['categorie', 'photos'])->loadSum([
                'locations as locations_reservees_count' => fn (Builder $query) => $query
                    ->reserveStock(),
            ], 'quantite'))->resolve($request),
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function journaliser(Request $request, string $action, Materiel $materiel, array $details = []): void
    {
        $this->journal->enregistrer(
            $request->user(),
            $action,
            AdminAction::CIBLE_MATERIEL,
            $materiel->getKey(),
            $materiel->nom,
            $details,
        );
    }

    private function ensureOwner(Request $request, Materiel $materiel): void
    {
        abort_unless(
            $materiel->user_id === $request->user()->getAuthIdentifier(),
            403,
            'Vous n’êtes pas propriétaire de ce matériel.',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function materialAttributes(array $validated): array
    {
        $attributes = Arr::except($validated, ['categorie', 'photo', 'photos']);
        $attributes['etat'] = $validated['etats_exemplaires'][0];

        if (array_key_exists('categorie_id', $validated)) {
            $attributes['categorie_id'] = $validated['categorie_id'];
        } else {
            abort_if(
                Category::onlyTrashed()->where('name', $validated['categorie'])->exists(),
                422,
                'Cette catégorie n’est plus disponible.',
            );

            $attributes['categorie_id'] = Category::query()->firstOrCreate([
                'name' => $validated['categorie'],
            ])->getKey();
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<UploadedFile>
     */
    private function uploadedPhotos(array $validated): array
    {
        return $validated['photos'] ?? [];
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<string>
     */
    private function storePhotos(array $photos): array
    {
        $paths = [];

        try {
            foreach ($photos as $photo) {
                $path = $photo->store('materiels', 'public');

                if ($path === false) {
                    throw new RuntimeException('Impossible d’enregistrer la photo du matériel.');
                }

                $paths[] = $path;
            }
        } catch (Throwable $exception) {
            $this->deletePhotos($paths);
            throw $exception;
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     */
    private function createPhotoRows(Materiel $materiel, array $paths): void
    {
        foreach ($paths as $position => $path) {
            $materiel->photos()->create([
                'path' => $path,
                'position' => $position,
            ]);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function deletePhotos(array $paths): void
    {
        $disk = Storage::disk('public');

        foreach ($paths as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Impossible de supprimer une photo du matériel.');
            }
        }
    }
}
