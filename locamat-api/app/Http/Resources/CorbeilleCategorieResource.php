<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorbeilleCategorieResource extends JsonResource
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
            'deleted_at' => $this->deleted_at,
            'purge_le' => $this->deleted_at?->copy()->addDays((int) config('corbeille.retention_days')),
            'supprime_par' => $this->supprime_par,
        ];
    }
}
