<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorbeilleMaterielResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locationsCount = (int) ($this->locations_count ?? 0);

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'photo' => $this->photos->first()?->path,
            'prix_journalier' => $this->prix_journalier,
            'proprietaire' => $this->proprietaire === null ? null : [
                'id' => $this->proprietaire->id,
                'name' => $this->proprietaire->name,
                'dans_la_corbeille' => $this->proprietaire->trashed(),
            ],
            'deleted_at' => $this->deleted_at,
            'supprime_par' => $this->supprime_par,
            'purge_le' => $this->deleted_at?->copy()->addDays((int) config('corbeille.retention_days')),
            'locations_count' => $locationsCount,
            // Un matériel rattaché à des locations n'est jamais détruit : l'historique des achats le référence.
            'conserve_pour_historique' => $locationsCount > 0,
        ];
    }
}
