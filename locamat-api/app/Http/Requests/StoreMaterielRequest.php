<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterielRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:255'],
            'categorie' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_journalier' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'etat' => ['required', 'string', Rule::in(['neuf', 'bon', 'usé'])],
            'disponible' => ['sometimes', 'boolean'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,png,webp', 'max:2048'],
        ];
    }
}
