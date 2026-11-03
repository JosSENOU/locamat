<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
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
            'materiel_id' => ['required', 'integer', 'exists:materiels,id'],
            'date_debut' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'message' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_debut.after_or_equal' => 'La date de début doit être aujourd’hui ou une date ultérieure.',
            'date_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
        ];
    }
}
