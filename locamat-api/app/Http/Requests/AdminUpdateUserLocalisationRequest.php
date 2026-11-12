<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateUserLocalisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ville' => ['required', 'string', 'max:100'],
            'quartier' => ['nullable', 'string', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ville.required' => 'La ville est obligatoire : elle indique où récupérer le matériel.',
        ];
    }
}
