<?php

namespace App\Http\Requests;

use App\Models\Reclamation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReclamationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', 'string', Rule::in(Reclamation::STATUTS)],
            'note_admin' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
