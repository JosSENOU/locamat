<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProprietaireDemandesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => [
                'sometimes',
                'nullable',
                Rule::in([
                    Location::STATUT_EN_ATTENTE,
                    Location::STATUT_CONFIRMEE,
                    Location::STATUT_REFUSEE,
                    Location::STATUT_ANNULEE,
                    Location::STATUT_TERMINEE,
                ]),
            ],
        ];
    }
}
