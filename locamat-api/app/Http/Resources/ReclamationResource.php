<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReclamationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $request->user()?->loadMissing('roles');
        $isAdministrator = $request->user()?->isAdministrator() ?? false;

        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'motif' => $this->motif,
            'description' => $this->description,
            'statut' => $this->statut,
            'note_admin' => $this->when(
                $isAdministrator || $this->locataire_id === $request->user()?->getKey(),
                $this->note_admin,
            ),
            'traitee_at' => $this->traitee_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'locataire' => $this->when($isAdministrator && $this->relationLoaded('locataire'), fn (): array => [
                'id' => $this->locataire->id,
                'name' => $this->locataire->name,
                'email' => $this->locataire->email,
            ]),
            'proprietaire' => $this->when($isAdministrator && $this->relationLoaded('proprietaire'), fn (): array => [
                'id' => $this->proprietaire->id,
                'name' => $this->proprietaire->name,
                'email' => $this->proprietaire->email,
            ]),
            'location' => $this->whenLoaded('location', function (): ?array {
                if ($this->location === null) {
                    return null;
                }

                return [
                    'id' => $this->location->id,
                    'date_debut' => $this->location->date_debut?->toDateString(),
                    'date_fin' => $this->location->date_fin?->toDateString(),
                    'statut' => $this->location->statut,
                    'materiel' => $this->location->materiel === null ? null : [
                        'id' => $this->location->materiel->id,
                        'nom' => $this->location->materiel->nom,
                    ],
                ];
            }),
        ];
    }
}
