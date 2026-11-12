<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Models\User;
use App\Rules\NumeroTelephone;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    use NormaliseTelephone;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proprietaire = $this->registersAsProprietaire();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            // Un propriétaire doit pouvoir être joint (WhatsApp) et localisé.
            'telephone' => $proprietaire
                ? ['required', 'string', new NumeroTelephone]
                : ['sometimes', 'nullable', 'string', new NumeroTelephone],
            'ville' => $proprietaire
                ? ['required', 'string', 'max:100']
                : ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:150'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'required', 'prohibits:roles', Rule::in([
                User::ROLE_LOCATAIRE,
                User::ROLE_PROPRIETAIRE,
            ])],
            'roles' => ['sometimes', 'required', 'array', 'min:1', 'prohibits:role'],
            'roles.*' => ['required', 'distinct', Rule::in([
                User::ROLE_LOCATAIRE,
                User::ROLE_PROPRIETAIRE,
            ])],
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

    public function registersAsProprietaire(): bool
    {
        $roles = $this->input('roles');

        return $this->input('role') === User::ROLE_PROPRIETAIRE
            || (is_array($roles) && in_array(User::ROLE_PROPRIETAIRE, $roles, true));
    }
}
