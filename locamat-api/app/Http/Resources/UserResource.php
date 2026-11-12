<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->primaryRole(),
            'roles' => $this->roles->pluck('name')->values(),
            'telephone' => $this->telephone,
            'ville' => $this->ville,
            'quartier' => $this->quartier,
            'adresse' => $this->adresse,
            'photo' => $this->isAdministrator() ? null : $this->photo,
        ];
    }
}
