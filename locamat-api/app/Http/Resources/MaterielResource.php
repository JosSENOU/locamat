<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterielResource extends JsonResource
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
            'user_id' => $this->user_id,
            'proprietaire_id' => $this->user_id,
            'nom' => $this->nom,
            'categorie_id' => $this->categorie_id,
            'categorie' => $this->whenLoaded('categorie', fn (): ?string => $this->categorie?->name),
            'description' => $this->description,
            'photo' => $this->photos->first()?->path,
            'photos' => $this->photos->pluck('path')->values(),
            'prix_journalier' => $this->prix_journalier,
            'etat' => $this->etat,
            'etats_exemplaires' => $this->etats_exemplaires
                ?? array_fill(0, max(1, (int) $this->quantite), $this->etat),
            'disponible' => $this->disponible,
            'quantite' => $this->quantite,
            'quantite_disponible' => $this->when(
                array_key_exists('locations_reservees_count', $this->resource->getAttributes()),
                fn (): int => max(0, (int) $this->quantite - (int) $this->locations_reservees_count),
            ),
            'locations_count' => $this->whenCounted('locations'),
            'locations_confirmees_count' => $this->when(
                array_key_exists('locations_reservees_count', $this->resource->getAttributes()),
                fn (): int => (int) $this->locations_reservees_count,
            ),
            'proprietaire' => $this->whenLoaded('proprietaire', fn (): array => [
                'id' => $this->proprietaire->id,
                'name' => $this->proprietaire->name,
                'ville' => $this->proprietaire->ville,
                'quartier' => $this->proprietaire->quartier,
                'photo' => $this->proprietaire->isAdministrator() ? null : $this->proprietaire->photo,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
