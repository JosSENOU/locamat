<?php

namespace App\Http\Requests;

use App\Models\Materiel;

class UpdateMaterielRequest extends StoreMaterielRequest
{
    public function authorize(): bool
    {
        $materiel = $this->route('materiel');

        return $materiel instanceof Materiel
            && $this->user()?->getAuthIdentifier() === $materiel->proprietaire_id;
    }
}
