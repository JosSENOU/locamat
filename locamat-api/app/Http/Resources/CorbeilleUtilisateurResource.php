<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorbeilleUtilisateurResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->primaryRole(),
            'roles' => $this->roles->pluck('name')->values(),
            'telephone' => $this->telephone,
            'deleted_at' => $this->deleted_at,
            'supprime_par' => $this->supprime_par,
            'purge_le' => $this->deleted_at?->copy()->addDays((int) config('corbeille.retention_days')),
            'anonymise' => $this->anonymise_at !== null,
            'locations_count' => (int) ($this->locations_count ?? 0),
            'materiels_count' => (int) ($this->materiels_count ?? 0),
        ];
    }
}
