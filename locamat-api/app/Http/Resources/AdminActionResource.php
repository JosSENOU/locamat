<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminActionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'acteur_id' => $this->user_id,
            'acteur_nom' => $this->acteur_nom,
            'acteur_role' => $this->acteur_role,
            'action' => $this->action,
            'cible_type' => $this->cible_type,
            'cible_id' => $this->cible_id,
            'cible_libelle' => $this->cible_libelle,
            'concerne_id' => $this->concerne_user_id,
            'details' => $this->details,
            'created_at' => $this->created_at,
        ];
    }
}
