<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\Location;
use App\Models\LocationHistorique;
use App\Models\Materiel;
use App\Models\User;
use App\Notifications\DemandeTraiteeNotification;
use App\Notifications\NouvelleDemandeLocationNotification;
use App\Notifications\ProlongationDemandeeNotification;
use App\Notifications\ProlongationTraiteeNotification;
use App\Notifications\RappelLocationNotification;
use Closure;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class LocationService
{
    public function __construct(private readonly JournalAdministration $journal) {}

    /**
     * Vérifie que `$quantiteDemandee` exemplaires restent disponibles sur toute la période.
     */
    public function datesLibres(
        int $materielId,
        string $debut,
        string $fin,
        ?int $exclureId = null,
        ?int $quantite = null,
        int $quantiteDemandee = 1,
    ): bool {
        $quantite ??= (int) Materiel::query()->findOrFail($materielId)->quantite;
        $debut = Carbon::parse($debut)->toDateString();
        $fin = Carbon::parse($fin)->toDateString();
        $aujourdHui = today()->toDateString();
        $evenements = [];

        $locations = Location::query()
            ->where('materiel_id', $materielId)
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->when($exclureId !== null, fn ($query) => $query->where('id', '!=', $exclureId))
            ->whereDate('date_debut', '<=', $fin)
            ->where(function ($query) use ($debut, $aujourdHui): void {
                $query->whereDate('date_fin', '>=', $debut)
                    ->orWhere(function ($overdueQuery) use ($aujourdHui): void {
                        $overdueQuery
                            ->whereDate('date_debut', '<=', $aujourdHui)
                            ->whereDate('date_fin', '<', $aujourdHui);
                    });
            })
            ->get(['date_debut', 'date_fin', 'quantite']);

        foreach ($locations as $location) {
            $dateDebut = max($debut, $location->date_debut->toDateString());
            $dateFin = $location->date_fin->toDateString() < $aujourdHui
                ? $fin
                : min($fin, $location->date_fin->toDateString());
            $dateFinExclusive = Carbon::parse($dateFin)
                ->addDay()
                ->toDateString();
            $evenements[$dateDebut] = ($evenements[$dateDebut] ?? 0) + $location->quantite;
            $evenements[$dateFinExclusive] = ($evenements[$dateFinExclusive] ?? 0) - $location->quantite;
        }

        ksort($evenements);
        $reservationsActives = 0;

        foreach ($evenements as $date => $variation) {
            $reservationsActives += $variation;

            if ($date <= $fin && $reservationsActives + $quantiteDemandee > $quantite) {
                return false;
            }
        }

        return $quantiteDemandee <= $quantite;
    }

    /**
     * @return list<array{date_debut: string, date_fin: ?string}>
     */
    public function periodesIndisponibles(Materiel $materiel): array
    {
        $aujourdHui = today()->toDateString();
        $evenements = [];

        $locationsEnRetard = (int) $materiel->locations()
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->whereDate('date_debut', '<=', $aujourdHui)
            ->whereDate('date_fin', '<', $aujourdHui)
            ->sum('quantite');

        if ($locationsEnRetard >= (int) $materiel->quantite) {
            return [[
                'date_debut' => $aujourdHui,
                'date_fin' => null,
            ]];
        }

        $locations = $materiel->locations()
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->whereDate('date_fin', '>=', $aujourdHui)
            ->get(['date_debut', 'date_fin', 'quantite']);

        foreach ($locations as $location) {
            $dateDebut = max($aujourdHui, $location->date_debut->toDateString());
            $dateFinExclusive = Carbon::parse($location->date_fin)->addDay()->toDateString();
            $evenements[$dateDebut] = ($evenements[$dateDebut] ?? 0) + $location->quantite;
            $evenements[$dateFinExclusive] = ($evenements[$dateFinExclusive] ?? 0) - $location->quantite;
        }

        ksort($evenements);
        $dates = array_keys($evenements);
        $reservationsActives = $locationsEnRetard;
        $periodes = [];

        foreach ($dates as $index => $date) {
            $reservationsActives += $evenements[$date];
            $prochaineDate = $dates[$index + 1] ?? null;

            if ($reservationsActives < (int) $materiel->quantite || $prochaineDate === null) {
                continue;
            }

            $dateFinIndisponible = Carbon::parse($prochaineDate)->subDay()->toDateString();
            $dernierePeriode = array_key_last($periodes);

            if (
                $dernierePeriode !== null
                && Carbon::parse($periodes[$dernierePeriode]['date_fin'])->addDay()->toDateString() === $date
            ) {
                $periodes[$dernierePeriode]['date_fin'] = $dateFinIndisponible;

                continue;
            }

            $periodes[] = [
                'date_debut' => $date,
                'date_fin' => $dateFinIndisponible,
            ];
        }

        return $periodes;
    }

    public function nombreMaximumLocationsSimultanees(int $materielId): int
    {
        $evenements = [];
        $aujourdHui = today()->toDateString();
        $locationsEnRetard = (int) Location::query()
            ->where('materiel_id', $materielId)
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->whereDate('date_debut', '<=', $aujourdHui)
            ->whereDate('date_fin', '<', $aujourdHui)
            ->sum('quantite');
        $locations = Location::query()
            ->where('materiel_id', $materielId)
            ->where('statut', Location::STATUT_CONFIRMEE)
            ->whereDate('date_fin', '>=', $aujourdHui)
            ->get(['date_debut', 'date_fin', 'quantite']);

        foreach ($locations as $location) {
            $dateDebut = max($aujourdHui, $location->date_debut->toDateString());
            $dateFinExclusive = Carbon::parse($location->date_fin)->addDay()->toDateString();
            $evenements[$dateDebut] = ($evenements[$dateDebut] ?? 0) + $location->quantite;
            $evenements[$dateFinExclusive] = ($evenements[$dateFinExclusive] ?? 0) - $location->quantite;
        }

        ksort($evenements);
        $reservationsActives = $locationsEnRetard;
        $maximum = $locationsEnRetard;

        foreach ($evenements as $variation) {
            $reservationsActives += $variation;
            $maximum = max($maximum, $reservationsActives);
        }

        return $maximum;
    }

    /**
     * @param  array{materiel_id: int, date_debut: string, date_fin: string, quantite?: int, message?: ?string}  $data
     */
    public function creerDemande(User $locataire, array $data): Location
    {
        $quantiteDemandee = max(1, (int) ($data['quantite'] ?? 1));

        $location = DB::transaction(function () use ($locataire, $data, $quantiteDemandee): Location {
            $materiel = Materiel::query()
                ->lockForUpdate()
                ->findOrFail($data['materiel_id']);

            abort_if(! $materiel->disponible, 422, 'Ce matériel est indisponible.');
            abort_if(
                (int) $materiel->user_id === (int) $locataire->getKey(),
                403,
                'Vous ne pouvez pas louer votre propre matériel.',
            );
            abort_unless(
                $quantiteDemandee <= (int) $materiel->quantite,
                422,
                'La quantité demandée dépasse le stock de ce matériel ('.(int) $materiel->quantite.').',
            );
            abort_unless(
                $this->datesLibres(
                    (int) $materiel->getKey(),
                    $data['date_debut'],
                    $data['date_fin'],
                    null,
                    (int) $materiel->quantite,
                    $quantiteDemandee,
                ),
                422,
                $quantiteDemandee > 1
                    ? 'Cette quantité n’est pas disponible sur la période choisie.'
                    : 'Ces dates chevauchent une location déjà confirmée.',
            );

            $nbJours = Location::nombreJoursInclus($data['date_debut'], $data['date_fin']);
            $prixJournalierEnCentimes = (int) round((float) $materiel->prix_journalier * 100);
            $prixTotal = number_format($nbJours * $prixJournalierEnCentimes * $quantiteDemandee / 100, 2, '.', '');

            $nouvelleLocation = $materiel->locations()->create([
                'user_id' => $locataire->getKey(),
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'],
                'nb_jours' => $nbJours,
                'quantite' => $quantiteDemandee,
                'prix_total' => $prixTotal,
                'statut' => Location::STATUT_EN_ATTENTE,
                'message' => $data['message'] ?? null,
            ]);

            $this->enregistrerHistorique($nouvelleLocation, LocationHistorique::CREATION, $locataire);

            return $nouvelleLocation;
        });

        $location->load(['materiel.proprietaire', 'locataire']);
        $this->notifier($location->materiel->proprietaire, new NouvelleDemandeLocationNotification($location));

        return $location;
    }

    public function confirmer(Location $location, User $proprietaire): Location
    {
        $location = $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation, Materiel $materiel) use ($proprietaire): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_EN_ATTENTE);
                abort_if(
                    $lockedLocation->date_debut->isBefore(today()),
                    422,
                    'Cette demande ne peut plus être confirmée : sa date de début est déjà passée. Vous pouvez la refuser.',
                );
                abort_unless(
                    $this->datesLibres(
                        (int) $materiel->getKey(),
                        $lockedLocation->date_debut->toDateString(),
                        $lockedLocation->date_fin->toDateString(),
                        (int) $lockedLocation->getKey(),
                        (int) $materiel->quantite,
                        (int) $lockedLocation->quantite,
                    ),
                    422,
                    'Ces dates chevauchent une location déjà confirmée.',
                );

                $lockedLocation->update(['statut' => Location::STATUT_CONFIRMEE]);
                $this->enregistrerHistorique($lockedLocation, LocationHistorique::CONFIRMATION, $proprietaire);
            },
        );

        $this->notifierDemandeTraitee($location, true);

        return $location;
    }

    public function refuser(Location $location, User $proprietaire, ?string $motifRefus = null): Location
    {
        $location = $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation) use ($motifRefus, $proprietaire): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_EN_ATTENTE);
                $lockedLocation->update([
                    'statut' => Location::STATUT_REFUSEE,
                    'motif_refus' => $motifRefus,
                ]);
                $this->enregistrerHistorique($lockedLocation, LocationHistorique::REFUS, $proprietaire, $motifRefus);
            },
        );

        $this->notifierDemandeTraitee($location, false);

        return $location;
    }

    public function terminer(Location $location, User $proprietaire): Location
    {
        return $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation, Materiel $materiel) use ($proprietaire): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_CONFIRMEE);
                abort_if(
                    ! $lockedLocation->date_fin->isBefore(today()),
                    422,
                    'La confirmation du retour sera possible à partir du lendemain de la date limite.',
                );
                $joursRetard = $lockedLocation->date_fin->isBefore(today())
                    ? (int) $lockedLocation->date_fin->diffInDays(today())
                    : 0;
                $prixJournalierEnCentimes = (int) round((float) $materiel->prix_journalier * 100)
                    * (int) $lockedLocation->quantite;
                $penaliteEnCentimes = Location::montantMajoreEnCentimes(
                    $joursRetard,
                    $prixJournalierEnCentimes,
                );

                $lockedLocation->update([
                    'statut' => Location::STATUT_TERMINEE,
                    'terminee_at' => now(),
                    'penalite_jours' => $joursRetard,
                    'penalite_montant' => number_format($penaliteEnCentimes / 100, 2, '.', ''),
                ]);
                $this->enregistrerHistorique(
                    $lockedLocation,
                    LocationHistorique::TERMINEE,
                    $proprietaire,
                    $joursRetard > 0
                        ? "Retour confirmé avec {$joursRetard} jour(s) de retard (pénalité : "
                            .number_format($penaliteEnCentimes / 100, 2, '.', '').').'
                        : 'Retour confirmé dans les délais.',
                );
            },
        );
    }

    public function annuler(Location $location, User $locataire): Location
    {
        return $this->withLockedLocation(
            $location,
            function (Location $lockedLocation, Materiel $materiel) use ($locataire): void {
                abort_unless(
                    (int) $lockedLocation->user_id === (int) $locataire->getKey(),
                    403,
                    'Vous ne pouvez pas annuler la demande d’un autre locataire.',
                );
            },
            function (Location $lockedLocation) use ($locataire): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_EN_ATTENTE);
                $lockedLocation->update(['statut' => Location::STATUT_ANNULEE]);
                $this->enregistrerHistorique($lockedLocation, LocationHistorique::ANNULATION, $locataire);
            },
        );
    }

    public function demanderProlongation(Location $location, User $locataire, string $dateFin): Location
    {
        $location = $this->withLockedLocation(
            $location,
            function (Location $lockedLocation, Materiel $materiel) use ($locataire): void {
                abort_unless(
                    (int) $lockedLocation->user_id === (int) $locataire->getKey(),
                    403,
                    'Vous ne pouvez pas prolonger la location d’un autre locataire.',
                );
            },
            function (Location $lockedLocation, Materiel $materiel) use ($dateFin, $locataire): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_CONFIRMEE);
                abort_if(
                    $lockedLocation->prolongation_statut === 'en_attente',
                    422,
                    'Une demande de prolongation est déjà en attente.',
                );

                $ancienneDateFin = Carbon::parse($lockedLocation->date_fin);
                $nouvelleDateFin = Carbon::parse($dateFin)->startOfDay();
                abort_if(
                    $ancienneDateFin->lessThan(today()),
                    422,
                    'Une demande de prolongation doit être envoyée avant la date limite.',
                );
                abort_if(
                    $nouvelleDateFin->lessThanOrEqualTo($ancienneDateFin),
                    422,
                    'La nouvelle date de retour doit être postérieure à la date actuelle.',
                );
                abort_if(
                    $nouvelleDateFin->lessThan(today()),
                    422,
                    'La nouvelle date de retour ne peut pas être passée.',
                );

                $joursAjoutes = (int) $ancienneDateFin->diffInDays($nouvelleDateFin);
                $prixJournalierEnCentimes = (int) round((float) $materiel->prix_journalier * 100)
                    * (int) $lockedLocation->quantite;
                $montantAvecMajoration = Location::montantMajoreEnCentimes(
                    $joursAjoutes,
                    $prixJournalierEnCentimes,
                );
                $montant = number_format($montantAvecMajoration / 100, 2, '.', '');

                $lockedLocation->update([
                    'prolongation_date_fin' => $dateFin,
                    'prolongation_montant' => $montant,
                    'prolongation_statut' => 'en_attente',
                ]);
                $this->enregistrerHistorique(
                    $lockedLocation,
                    LocationHistorique::PROLONGATION_DEMANDEE,
                    $locataire,
                    'Nouvelle date de retour demandée : '.$nouvelleDateFin->format('d/m/Y').'.',
                );
            },
        );

        $location->load(['materiel.proprietaire', 'locataire']);
        $this->notifier($location->materiel->proprietaire, new ProlongationDemandeeNotification($location));

        return $location;
    }

    public function confirmerProlongation(Location $location, User $proprietaire): Location
    {
        $location = $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation, Materiel $materiel) use ($proprietaire): void {
                $this->ensureStatus($lockedLocation, Location::STATUT_CONFIRMEE);
                abort_unless(
                    $lockedLocation->prolongation_statut === 'en_attente'
                        && $lockedLocation->prolongation_date_fin !== null
                        && $lockedLocation->prolongation_montant !== null,
                    422,
                    'Aucune demande de prolongation n’est en attente.',
                );

                $ancienneDateFin = Carbon::parse($lockedLocation->date_fin);
                $nouvelleDateFin = Carbon::parse($lockedLocation->prolongation_date_fin);
                abort_unless(
                    $this->datesLibres(
                        (int) $materiel->getKey(),
                        $ancienneDateFin->copy()->addDay()->toDateString(),
                        $nouvelleDateFin->toDateString(),
                        (int) $lockedLocation->getKey(),
                        (int) $materiel->quantite,
                        (int) $lockedLocation->quantite,
                    ),
                    422,
                    'La période demandée chevauche une autre location confirmée.',
                );

                $joursAjoutes = (int) $ancienneDateFin->diffInDays($nouvelleDateFin);
                $prixTotalEnCentimes = (int) round((float) $lockedLocation->prix_total * 100)
                    + (int) round((float) $lockedLocation->prolongation_montant * 100);

                $lockedLocation->update([
                    'date_fin_initiale' => $lockedLocation->date_fin_initiale ?? $lockedLocation->date_fin,
                    'date_fin' => $nouvelleDateFin->toDateString(),
                    'nb_jours' => $lockedLocation->nb_jours + $joursAjoutes,
                    'prix_total' => number_format($prixTotalEnCentimes / 100, 2, '.', ''),
                    'prolongation_statut' => 'confirmee',
                ]);
                $this->enregistrerHistorique(
                    $lockedLocation,
                    LocationHistorique::PROLONGATION_ACCEPTEE,
                    $proprietaire,
                    'Nouvelle date de retour : '.$nouvelleDateFin->format('d/m/Y').'.',
                );
            },
        );

        $this->notifierProlongationTraitee($location, true);

        return $location;
    }

    public function refuserProlongation(Location $location, User $proprietaire): Location
    {
        $location = $this->withLockedLocation(
            $location,
            fn (Location $lockedLocation, Materiel $materiel) => $this->ensureProprietaire($materiel, $proprietaire),
            function (Location $lockedLocation) use ($proprietaire): void {
                abort_unless(
                    $lockedLocation->prolongation_statut === 'en_attente',
                    422,
                    'Aucune demande de prolongation n’est en attente.',
                );

                $lockedLocation->update(['prolongation_statut' => 'refusee']);
                $this->enregistrerHistorique($lockedLocation, LocationHistorique::PROLONGATION_REFUSEE, $proprietaire);
            },
        );

        $this->notifierProlongationTraitee($location, false);

        return $location;
    }

    /**
     * Envoie les rappels de remise et de retour, puis l'alerte de retour en retard.
     *
     * @return int Nombre de locations pour lesquelles un rappel a été envoyé.
     */
    public function envoyerRappels(int $joursAvant): int
    {
        $aujourdHui = today();
        $envoyes = 0;
        $echeances = [
            [RappelLocationNotification::TYPE_REMISE, 'date_debut', $aujourdHui->copy()->addDays($joursAvant), $joursAvant, 'tous'],
            [RappelLocationNotification::TYPE_RETOUR, 'date_fin', $aujourdHui->copy()->addDays($joursAvant), $joursAvant, 'tous'],
            [RappelLocationNotification::TYPE_RETARD, 'date_fin', $aujourdHui->copy()->subDay(), $joursAvant, 'tous'],
            // Le propriétaire peut confirmer le retour à partir du lendemain de l'échéance.
            [RappelLocationNotification::TYPE_CONFIRMATION, 'date_fin', $aujourdHui->copy()->subDay(), 0, 'proprietaire'],
        ];

        // Rappel de la dernière minute : la veille de la date de retour, uniquement pour le locataire.
        if ($joursAvant !== 1) {
            $echeances[] = [RappelLocationNotification::TYPE_RETOUR, 'date_fin', $aujourdHui->copy()->addDay(), 1, 'locataire'];
        }

        foreach ($echeances as [$type, $colonne, $date, $jours, $destinataires]) {
            Location::query()
                ->with(['materiel.proprietaire', 'locataire'])
                ->where('statut', Location::STATUT_CONFIRMEE)
                ->whereDate($colonne, $date->toDateString())
                ->each(function (Location $location) use ($type, $jours, $destinataires, &$envoyes): void {
                    $envoye = false;

                    if ($destinataires !== 'proprietaire' && ! $this->rappelDejaEnvoye($location->locataire, $location, $type)) {
                        $this->notifier(
                            $location->locataire,
                            new RappelLocationNotification($location, $type, false, $jours),
                        );
                        $envoye = true;
                    }

                    if ($destinataires !== 'locataire' && ! $this->rappelDejaEnvoye($location->materiel->proprietaire, $location, $type)) {
                        $this->notifier(
                            $location->materiel->proprietaire,
                            new RappelLocationNotification($location, $type, true, $jours),
                        );
                        $envoye = true;
                    }

                    if ($envoye) {
                        $envoyes++;
                    }
                });
        }

        return $envoyes;
    }

    /** Fenêtre (en jours) avant la remise ou le retour dans laquelle le rappel apparaît dans l'application. */
    public const JOURS_RAPPEL_APPLI = 3;

    /**
     * Génère, à l'ouverture de l'application, les rappels dont `$user` a besoin : aucune tâche
     * planifiée, aucune heure fixe, l'ordinateur n'a pas besoin d'être allumé à un moment précis.
     *
     * Chaque rappel (remise, retour, retard, retour à confirmer) n'est créé qu'une seule fois par
     * location et par personne, dans l'application uniquement (pas d'e-mail). Une échéance
     * dépassée est rattrapée dès la prochaine ouverture.
     *
     * @return int Nombre de rappels créés.
     */
    public function genererRappelsPour(User $user): int
    {
        $aujourdHui = today();
        $limite = $aujourdHui->copy()->addDays(self::JOURS_RAPPEL_APPLI);
        $crees = 0;

        $enregistrer = function (Location $location, User $destinataire, string $type, bool $proprietaire, int $jours) use (&$crees): void {
            if ($this->rappelDejaCree($destinataire, $location, $type)) {
                return;
            }

            $this->notifier(
                $destinataire,
                new RappelLocationNotification($location, $type, $proprietaire, $jours, avecMail: false),
            );
            $crees++;
        };

        $confirmees = fn () => Location::query()
            ->with(['materiel.proprietaire', 'locataire'])
            ->where('statut', Location::STATUT_CONFIRMEE);

        // En tant que locataire.
        $confirmees()->where('user_id', $user->getKey())->each(
            function (Location $location) use ($user, $aujourdHui, $limite, $enregistrer): void {
                if ($location->date_debut->gte($aujourdHui) && $location->date_debut->lte($limite)) {
                    $enregistrer($location, $user, RappelLocationNotification::TYPE_REMISE, false, (int) $aujourdHui->diffInDays($location->date_debut));
                }

                if ($location->date_fin->gte($aujourdHui) && $location->date_fin->lte($limite)) {
                    $enregistrer($location, $user, RappelLocationNotification::TYPE_RETOUR, false, (int) $aujourdHui->diffInDays($location->date_fin));
                }

                if ($location->date_fin->lt($aujourdHui)) {
                    $enregistrer($location, $user, RappelLocationNotification::TYPE_RETARD, false, 0);
                }
            },
        );

        // En tant que propriétaire du matériel.
        $confirmees()->whereHas('materiel', fn ($query) => $query->withTrashed()->where('user_id', $user->getKey()))->each(
            function (Location $location) use ($user, $aujourdHui, $limite, $enregistrer): void {
                if ($location->date_debut->gte($aujourdHui) && $location->date_debut->lte($limite)) {
                    $enregistrer($location, $user, RappelLocationNotification::TYPE_REMISE, true, (int) $aujourdHui->diffInDays($location->date_debut));
                }

                if ($location->date_fin->gte($aujourdHui) && $location->date_fin->lte($limite)) {
                    $enregistrer($location, $user, RappelLocationNotification::TYPE_RETOUR, true, (int) $aujourdHui->diffInDays($location->date_fin));
                }

                // Le retour peut être confirmé à partir du lendemain de l'échéance.
                if ($location->date_fin->lt($aujourdHui)) {
                    $enregistrer($location, $user, RappelLocationNotification::TYPE_CONFIRMATION, true, 0);
                }
            },
        );

        return $crees;
    }

    /**
     * Un rappel d'un type donné n'est créé qu'une seule fois par location et par personne.
     */
    private function rappelDejaCree(User $destinataire, Location $location, string $type): bool
    {
        return $destinataire->notifications()
            ->where('data->type', 'rappel_'.$type)
            ->where('data->location_id', $location->getKey())
            ->exists();
    }

    /**
     * Un même rappel n'est envoyé qu'une fois par jour et par personne, même si la commande est relancée.
     */
    private function rappelDejaEnvoye(User $destinataire, Location $location, string $type): bool
    {
        return $destinataire->notifications()
            ->where('created_at', '>=', today())
            ->where('data->type', 'rappel_'.$type)
            ->where('data->location_id', $location->getKey())
            ->exists();
    }

    /**
     * Garde une trace datée de chaque changement de statut d'une location.
     */
    private function enregistrerHistorique(
        Location $location,
        string $evenement,
        ?User $acteur = null,
        ?string $note = null,
    ): void {
        $location->historiques()->create([
            'user_id' => $acteur?->getKey(),
            'evenement' => $evenement,
            'statut' => $location->statut,
            'note' => $note !== null && $note !== '' ? $note : null,
        ]);

        $action = AdminAction::ACTIONS_LOCATION[$evenement] ?? null;

        if ($action === null) {
            return;
        }

        $location->loadMissing(['materiel.proprietaire:id,name', 'locataire:id,name']);
        $locataireId = (int) $location->user_id;
        $proprietaireId = $location->materiel?->user_id;

        $this->journal->enregistrer(
            $acteur,
            $action,
            AdminAction::CIBLE_LOCATION,
            $location->getKey(),
            $location->materiel?->nom,
            array_filter([
                'locataire_id' => $locataireId,
                'locataire' => $location->locataire?->name,
                'proprietaire_id' => $proprietaireId,
                'proprietaire' => $location->materiel?->proprietaire?->name,
                'date_debut' => $location->date_debut?->toDateString(),
                'date_fin' => $location->date_fin?->toDateString(),
                'quantite' => $location->quantite,
                'prix_total' => $location->prix_total,
                'note' => $note,
            ], fn (mixed $valeur): bool => $valeur !== null && $valeur !== ''),
            $acteur?->getKey() === $locataireId ? $proprietaireId : $locataireId,
        );
    }

    private function notifierDemandeTraitee(Location $location, bool $confirmee): void
    {
        $location->load(['materiel', 'locataire']);
        $this->notifier($location->locataire, new DemandeTraiteeNotification($location, $confirmee));
    }

    private function notifierProlongationTraitee(Location $location, bool $acceptee): void
    {
        $location->load(['materiel', 'locataire']);
        $this->notifier($location->locataire, new ProlongationTraiteeNotification($location, $acceptee));
    }

    /**
     * L'envoi d'un e-mail ne doit jamais faire échouer l'action déjà enregistrée.
     */
    private function notifier(User $destinataire, Notification $notification): void
    {
        try {
            $destinataire->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
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
            (int) $materiel->user_id === (int) $proprietaire->getKey(),
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
