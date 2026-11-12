<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
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
            'ville' => $this->ville,
            'quartier' => $this->quartier,
            'adresse' => $this->adresse,
            'photo' => $this->isAdministrator() ? null : $this->photo,
            'is_active' => $this->is_active,
            'is_locked' => $this->login_locked_at !== null
                && $this->login_locked_at->copy()->addHours(12)->isFuture(),
            'materiels_count' => $this->whenCounted('materiels'),
            'created_at' => $this->created_at,
        ];
    }
}
