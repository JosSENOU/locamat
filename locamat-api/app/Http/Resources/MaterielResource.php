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
            'proprietaire_id' => $this->proprietaire_id,
            'nom' => $this->nom,
            'categorie' => $this->categorie,
            'description' => $this->description,
            'photo' => $this->photo,
            'prix_journalier' => $this->prix_journalier,
            'etat' => $this->etat,
            'disponible' => $this->disponible,
            'locations_count' => $this->whenCounted('locations'),
            'proprietaire' => $this->whenLoaded('proprietaire', fn (): array => [
                'id' => $this->proprietaire->id,
                'name' => $this->proprietaire->name,
                'photo' => $this->proprietaire->role === 'admin' ? null : $this->proprietaire->photo,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
