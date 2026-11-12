<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Models\User;
use App\Rules\NumeroTelephone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proprietaire = $this->user()?->hasRole(User::ROLE_PROPRIETAIRE) ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'telephone' => $proprietaire
                ? ['required', 'string', new NumeroTelephone]
                : ['nullable', 'string', new NumeroTelephone],
            'ville' => $proprietaire
                ? ['required', 'string', 'max:100']
                : ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:150'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'photo' => $this->user()?->hasRole('admin')
                ? ['prohibited']
                : ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => $this->user()?->hasRole('admin')
                ? ['prohibited']
                : ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'telephone.required' => 'Le téléphone est obligatoire pour un propriétaire : les locataires vous contactent par WhatsApp.',
            'ville.required' => 'La ville est obligatoire pour un propriétaire : elle indique où récupérer le matériel.',
        ];
    }
}
