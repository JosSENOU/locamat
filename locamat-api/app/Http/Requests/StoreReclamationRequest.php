<?php

namespace App\Http\Requests;

use App\Models\Reclamation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReclamationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'motif' => ['required', 'string', Rule::in(Reclamation::MOTIFS)],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }
}
