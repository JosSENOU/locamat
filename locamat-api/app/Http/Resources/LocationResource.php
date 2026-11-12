<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'materiel_id' => $this->materiel_id,
            'user_id' => $this->user_id,
            'locataire_id' => $this->user_id,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
            'date_fin_initiale' => ($this->date_fin_initiale ?? $this->date_fin)->toDateString(),
            'en_retard' => $this->estEnRetard(),
            'nb_jours' => $this->nb_jours,
            'quantite' => (int) ($this->quantite ?? 1),
            'prix_total' => $this->prix_total,
            'statut' => $this->statut,
            'message' => $this->message,
            'motif_refus' => $this->motif_refus,
            'terminee_at' => $this->terminee_at,
            'prolongation_date_fin' => $this->prolongation_date_fin?->toDateString(),
            'prolongation_montant' => $this->prolongation_montant,
            'prolongation_statut' => $this->prolongation_statut,
            'penalite_jours' => $this->penaliteJours(),
            'penalite_montant' => $this->penaliteMontant(),
            'montant_total_a_payer' => $this->montantTotalAPayer(),
            'materiel' => $this->whenLoaded('materiel', function () use ($request): array {
                $materiel = (new MaterielResource($this->materiel))->resolve($request);

                if ($this->materiel->relationLoaded('proprietaire') && $this->materiel->proprietaire !== null) {
                    $proprietaire = $this->materiel->proprietaire;
                    $coordonneesVisibles = in_array($this->statut, [
                        Location::STATUT_CONFIRMEE,
                        Location::STATUT_TERMINEE,
                    ], true);
                    $materiel['proprietaire'] = [
                        'id' => $proprietaire->id,
                        'name' => $proprietaire->name,
                        'photo' => $proprietaire->isAdministrator() ? null : $proprietaire->photo,
                        'ville' => $proprietaire->ville,
                        'quartier' => $proprietaire->quartier,
                        'telephone' => $coordonneesVisibles ? $proprietaire->telephone : null,
                        'adresse' => $coordonneesVisibles ? $proprietaire->adresse : null,
                    ];
                }

                return $materiel;
            }),
            'locataire' => $this->whenLoaded(
                'locataire',
                fn (): array => (new UserResource($this->locataire))->resolve($request),
            ),
            'historiques' => $this->whenLoaded('historiques', fn (): array => $this->historiques
                ->map(fn ($historique): array => [
                    'id' => $historique->id,
                    'evenement' => $historique->evenement,
                    'statut' => $historique->statut,
                    'note' => $historique->note,
                    'acteur' => $historique->relationLoaded('acteur') && $historique->acteur !== null
                        ? ['id' => $historique->acteur->id, 'name' => $historique->acteur->name]
                        : null,
                    'created_at' => $historique->created_at,
                ])
                ->values()
                ->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function penaliteJours(): int
    {
        if ($this->statut === Location::STATUT_TERMINEE) {
            return (int) ($this->penalite_jours ?? 0);
        }

        if ($this->statut !== Location::STATUT_CONFIRMEE || ! $this->date_fin->isBefore(today())) {
            return 0;
        }

        return (int) $this->date_fin->diffInDays(today());
    }

    private function penaliteMontant(): string
    {
        if ($this->statut === Location::STATUT_TERMINEE) {
            return $this->penalite_montant ?? '0.00';
        }

        $joursRetard = $this->penaliteJours();
        if ($joursRetard === 0) {
            return '0.00';
        }

        $prixJournalierEnCentimes = (int) round((float) $this->materiel->prix_journalier * 100)
            * (int) ($this->quantite ?? 1);
        $penaliteEnCentimes = Location::montantMajoreEnCentimes($joursRetard, $prixJournalierEnCentimes);

        return number_format($penaliteEnCentimes / 100, 2, '.', '');
    }

    private function montantTotalAPayer(): string
    {
        $montantTotalEnCentimes = (int) round((float) $this->prix_total * 100)
            + (int) round((float) $this->penaliteMontant() * 100);

        return number_format($montantTotalEnCentimes / 100, 2, '.', '');
    }
}
