<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminLocationsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'statut' => ['sometimes', Rule::in([
                Location::STATUT_EN_ATTENTE,
                Location::STATUT_CONFIRMEE,
                Location::STATUT_REFUSEE,
                Location::STATUT_ANNULEE,
                Location::STATUT_TERMINEE,
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
