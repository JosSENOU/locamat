<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LocationService
{
    public function datesLibres(
        int $materielId,
        string $debut,
        string $fin,
        ?int $exclureId = null,
    ): bool {
        return ! Location::query()
            ->where('materiel_id', $materielId)
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->when($exclureId !== null, fn ($query) => $query->where('id', '!=', $exclureId))
            ->where('date_debut', '<=', Carbon::parse($fin)->endOfDay())
            ->where('date_fin', '>=', Carbon::parse($debut)->startOfDay())
            ->exists();
    }

    /**
     * @param  array{materiel_id: int, date_debut: string, date_fin: string, message?: ?string}  $data
     */
    public function creerDemande(User $locataire, array $data): Location
    {
        return DB::transaction(function () use ($locataire, $data): Location {
            $materiel = Materiel::query()
                ->lockForUpdate()
                ->findOrFail($data['materiel_id']);

            abort_if(! $materiel->disponible, 422, 'Ce matériel est indisponible.');
            abort_if(
                (int) $materiel->proprietaire_id === (int) $locataire->getKey(),
                403,
                'Vous ne pouvez pas louer votre propre matériel.',
            );
            abort_unless(
                $this->datesLibres($materiel->getKey(), $data['date_debut'], $data['date_fin']),
                422,
                'Ces dates chevauchent une location déjà confirmée.',
            );

            $nbJours = (int) Carbon::parse($data['date_debut'])
                ->diffInDays(Carbon::parse($data['date_fin'])) + 1;
            $prixJournalierEnCentimes = (int) round((float) $materiel->prix_journalier * 100);
            $prixTotal = number_format($nbJours * $prixJournalierEnCentimes / 100, 2, '.', '');

            return $materiel->locations()->create([
                'locataire_id' => $locataire->getKey(),
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'],
                'nb_jours' => $nbJours,
                'prix_total' => $prixTotal,
                'statut' => Location::STATUT_EN_ATTENTE,
                'message' => $data['message'] ?? null,
            ]);
        });
    }

    public function confirmer(Location $location, User $proprietaire): Location
    {
        return $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation, Materiel $materiel): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_EN_ATTENTE);
                abort_unless(
                    $this->datesLibres(
                        (int) $materiel->getKey(),
                        $lockedLocation->date_debut->toDateString(),
                        $lockedLocation->date_fin->toDateString(),
                        (int) $lockedLocation->getKey(),
                    ),
                    422,
                    'Ces dates chevauchent une location déjà confirmée.',
                );

                $lockedLocation->update(['statut' => Location::STATUT_CONFIRMEE]);
            },
        );
    }

    public function refuser(Location $location, User $proprietaire, ?string $motifRefus = null): Location
    {
        return $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation) use ($motifRefus): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_EN_ATTENTE);
                $lockedLocation->update([
                    'statut' => Location::STATUT_REFUSEE,
                    'motif_refus' => $motifRefus,
                ]);
            },
        );
    }

    public function terminer(Location $location, User $proprietaire): Location
    {
        return $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_CONFIRMEE);
                $lockedLocation->update([
                    'statut' => Location::STATUT_TERMINEE,
                    'terminee_at' => now(),
                ]);
            },
        );
    }

    public function annuler(Location $location, User $locataire): Location
    {
        return $this->withLockedLocation(
            $location,
            function (Location $lockedLocation, Materiel $materiel) use ($locataire): void {
                abort_unless(
                    (int) $lockedLocation->locataire_id === (int) $locataire->getKey(),
                    403,
                    'Vous ne pouvez pas annuler la demande d’un autre locataire.',
                );
            },
            function (Location $lockedLocation): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_EN_ATTENTE);
                $lockedLocation->update(['statut' => Location::STATUT_ANNULEE]);
            },
        );
    }

    /**
     * @param  Closure(Location, Materiel): void  $authorize
     * @param  Closure(Location, Materiel): void  $operation
     */
    private function withLockedLocation(
        Location $location,
        Closure $authorize,
        Closure $operation,
    ): Location {
        return DB::transaction(function () use ($location, $authorize, $operation): Location {
            $materiel = Materiel::query()
                ->lockForUpdate()
                ->findOrFail($location->materiel_id);
            $lockedLocation = $materiel->locations()
                ->lockForUpdate()
                ->findOrFail($location->getKey());

            $authorize($lockedLocation, $materiel);
            $operation($lockedLocation, $materiel);

            return $lockedLocation->refresh();
        });
    }

    private function ensureProprietaire(Materiel $materiel, User $proprietaire): void
    {
        abort_unless(
            (int) $materiel->proprietaire_id === (int) $proprietaire->getKey(),
            403,
            'Vous n’êtes pas propriétaire de ce matériel.',
        );
    }

    private function ensureStatus(Location $location, string $expectedStatus): void
    {
        abort_unless(
            $location->statut === $expectedStatus,
            422,
            'Cette action est impossible pour le statut actuel de la location.',
        );
    }
}
