<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AchatsIndexRequest extends FormRequest
{
    public const TOUS_LES_STATUTS = 'tous';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sans `statut`, seuls les achats effectifs (locations confirmées ou terminées) sont listés.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'statut' => ['sometimes', Rule::in([
                self::TOUS_LES_STATUTS,
                Location::STATUT_EN_ATTENTE,
                Location::STATUT_CONFIRMEE,
                Location::STATUT_REFUSEE,
                Location::STATUT_ANNULEE,
                Location::STATUT_TERMINEE,
            ])],
            'du' => ['sometimes', 'date_format:Y-m-d'],
            'au' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:du'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}