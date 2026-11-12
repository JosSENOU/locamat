<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? null,
            'titre' => $this->data['titre'] ?? '',
            'message' => $this->data['message'] ?? '',
            'lien' => $this->data['lien'] ?? null,
            'location_id' => $this->data['location_id'] ?? null,
            'materiel_id' => $this->data['materiel_id'] ?? null,
            'lu' => $this->read_at !== null,
            'created_at' => $this->created_at,
        ];
    }
}
