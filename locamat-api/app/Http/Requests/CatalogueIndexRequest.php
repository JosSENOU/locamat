<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogueIndexRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'categorie' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prix_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'prix_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:prix_min'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
