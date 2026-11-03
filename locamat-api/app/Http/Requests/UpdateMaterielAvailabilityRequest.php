<?php

namespace App\Http\Requests;

use App\Models\Materiel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterielAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $materiel = $this->route('materiel');

        return $materiel instanceof Materiel
            && $this->user()?->getAuthIdentifier() === $materiel->proprietaire_id;
    }

    public function rules(): array
    {
        return [
            'disponible' => ['required', 'boolean'],
        ];
    }
}
