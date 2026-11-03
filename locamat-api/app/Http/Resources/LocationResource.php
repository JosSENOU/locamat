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
            'locataire_id' => $this->locataire_id,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'nb_jours' => $this->nb_jours,
            'prix_total' => $this->prix_total,
            'statut' => $this->statut,
            'message' => $this->message,
            'motif_refus' => $this->motif_refus,
            'terminee_at' => $this->terminee_at,
            'materiel' => $this->whenLoaded('materiel', function () use ($request): array {
                $materiel = (new MaterielResource($this->materiel))->resolve($request);

                if ($this->materiel->relationLoaded('proprietaire') && $this->materiel->proprietaire !== null) {
                    $proprietaire = $this->materiel->proprietaire;
                    $materiel['proprietaire'] = [
                        'id' => $proprietaire->id,
                        'name' => $proprietaire->name,
                        'photo' => $proprietaire->role === 'admin' ? null : $proprietaire->photo,
                        'telephone' => in_array($this->statut, [
                            Location::STATUT_CONFIRMEE,
                            Location::STATUT_TERMINEE,
                        ], true) ? $proprietaire->telephone : null,
                    ];
                }

                return $materiel;
            }),
            'locataire' => $this->whenLoaded(
                'locataire',
                fn (): array => (new UserResource($this->locataire))->resolve($request),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
