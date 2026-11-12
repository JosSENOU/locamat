<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Suivi comptable de la plateforme.
 *
 * Un « achat » est une location qui engage de l'argent : confirmée (montant engagé) ou terminée
 * (montant réalisé, pénalités de retard comprises). Les demandes en attente, refusées ou annulées
 * ne comptent pas. Les calculs se font en centimes pour éviter toute dérive d'arrondi.
 */
class ComptabiliteService
{
    public const STATUTS_ACHAT = [Location::STATUT_CONFIRMEE, Location::STATUT_TERMINEE];

    /**
     * Totaux d'un ensemble de locations (déjà filtré par l'appelant).
     *
     * @param  Builder<Location>  $locations
     * @return array{nombre_achats: int, montant_realise: string, penalites: string, total_realise: string, montant_en_cours: string}
     */
    public function totaux(Builder $locations): array
    {
        $realisees = (clone $locations)->where('locations.statut', Location::STATUT_TERMINEE);
        $enCours = (clone $locations)->where('locations.statut', Location::STATUT_CONFIRMEE);

        $montantRealise = $this->enCentimes((clone $realisees)->sum('prix_total'));
        $penalites = $this->enCentimes((clone $realisees)->sum('penalite_montant'));

        return [
            'nombre_achats' => (clone $locations)->whereIn('locations.statut', self::STATUTS_ACHAT)->count(),
            'montant_realise' => $this->formater($montantRealise),
            'penalites' => $this->formater($penalites),
            'total_realise' => $this->formater($montantRealise + $penalites),
            'montant_en_cours' => $this->formater($this->enCentimes($enCours->sum('prix_total'))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resume(): array
    {
        return [
            'totaux' => $this->totaux(Location::query()),
            'mois_courant' => $this->totaux(Location::query()->where('locations.created_at', '>=', now()->startOfMonth())),
            'par_statut' => $this->parStatut(),
            'mensuel' => $this->mensuel(),
            'par_categorie' => $this->parCategorie(),
            'top_proprietaires' => $this->topProprietaires(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function parStatut(): array
    {
        $comptes = Location::query()
            ->selectRaw('statut, count(*) as nombre')
            ->groupBy('statut')
            ->pluck('nombre', 'statut');

        return collect([
            Location::STATUT_EN_ATTENTE,
            Location::STATUT_CONFIRMEE,
            Location::STATUT_TERMINEE,
            Location::STATUT_REFUSEE,
            Location::STATUT_ANNULEE,
        ])->mapWithKeys(fn (string $statut): array => [$statut => (int) ($comptes[$statut] ?? 0)])->all();
    }

    /**
     * Achats des douze derniers mois, classés par mois de la demande.
     *
     * @return list<array{mois: string, nombre: int, montant: string}>
     */
    private function mensuel(): array
    {
        $debut = now()->startOfMonth()->subMonths(11);

        $achats = Location::query()
            ->whereIn('statut', self::STATUTS_ACHAT)
            ->where('created_at', '>=', $debut)
            ->get(['created_at', 'prix_total', 'penalite_montant'])
            ->groupBy(fn (Location $location): string => $location->created_at->format('Y-m'));

        return collect(range(0, 11))
            ->map(function (int $decalage) use ($debut, $achats): array {
                $mois = $debut->copy()->addMonths($decalage)->format('Y-m');
                $lignes = $achats->get($mois, collect());

                return [
                    'mois' => $mois,
                    'nombre' => $lignes->count(),
                    'montant' => $this->formater($lignes->sum(
                        fn (Location $location): int => $this->enCentimes($location->prix_total)
                            + $this->enCentimes($location->penalite_montant),
                    )),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{nom: string, nombre: int, montant: string}>
     */
    private function parCategorie(): array
    {
        return Location::query()
            ->join('materiels', 'materiels.id', '=', 'locations.materiel_id')
            ->join('categories', 'categories.id', '=', 'materiels.categorie_id')
            ->whereIn('locations.statut', self::STATUTS_ACHAT)
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('sum(locations.prix_total + coalesce(locations.penalite_montant, 0))'))
            ->limit(6)
            ->get([
                'categories.name as nom',
                DB::raw('count(*) as nombre'),
                DB::raw('sum(locations.prix_total + coalesce(locations.penalite_montant, 0)) as montant'),
            ])
            ->map(fn ($ligne): array => [
                'nom' => (string) $ligne->nom,
                'nombre' => (int) $ligne->nombre,
                'montant' => $this->formater($this->enCentimes($ligne->montant)),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, nom: string, nombre: int, montant: string}>
     */
    private function topProprietaires(): array
    {
        return Location::query()
            ->join('materiels', 'materiels.id', '=', 'locations.materiel_id')
            ->join('users', 'users.id', '=', 'materiels.user_id')
            ->whereIn('locations.statut', self::STATUTS_ACHAT)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc(DB::raw('sum(locations.prix_total + coalesce(locations.penalite_montant, 0))'))
            ->limit(5)
            ->get([
                'users.id as id',
                'users.name as nom',
                DB::raw('count(*) as nombre'),
                DB::raw('sum(locations.prix_total + coalesce(locations.penalite_montant, 0)) as montant'),
            ])
            ->map(fn ($ligne): array => [
                'id' => (int) $ligne->id,
                'nom' => (string) $ligne->nom,
                'nombre' => (int) $ligne->nombre,
                'montant' => $this->formater($this->enCentimes($ligne->montant)),
            ])
            ->all();
    }

    private function enCentimes(mixed $montant): int
    {
        return (int) round((float) $montant * 100);
    }

    private function formater(int $centimes): string
    {
        return number_format($centimes / 100, 2, '.', '');
    }
}
