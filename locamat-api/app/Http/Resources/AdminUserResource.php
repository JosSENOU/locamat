<?php

namespace App\Http\Resources;

use App\Models\User;
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
            'role' => $this->role,
            'telephone' => $this->telephone,
            'photo' => $this->role === User::ROLE_ADMIN ? null : $this->photo,
            'is_active' => $this->is_active,
            'materiels_count' => $this->whenCounted('materiels'),
            'created_at' => $this->created_at,
        ];
    }
}
