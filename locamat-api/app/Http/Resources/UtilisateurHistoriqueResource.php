<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilisateurHistoriqueResource extends JsonResource
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
            'telephone' => $this->telephone,
            'role' => $this->primaryRole(),
            'roles' => $this->roles->pluck('name')->values(),
            'is_active' => (bool) $this->is_active,
            'supprime' => $this->trashed(),
            'anonymise' => $this->anonymise_at !== null,
            'created_at' => $this->created_at,
            'actions_count' => (int) ($this->actions_count ?? 0),
            'derniere_activite' => $this->derniere_activite,
        ];
    }
}
