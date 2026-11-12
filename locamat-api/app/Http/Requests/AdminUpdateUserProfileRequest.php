<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Models\User;
use App\Rules\NumeroTelephone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification du profil d'un compte par un administrateur.
 * La localisation (ville, quartier, adresse) ne concerne que les propriétaires.
 */
class AdminUpdateUserProfileRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $proprietaire = $user instanceof User && $user->hasRole(User::ROLE_PROPRIETAIRE);

        return [
            'name' => ['required', 'string', 'max:255'],
            // Le rôle d'un compte ne change jamais, même pour un administrateur.
            'role' => ['prohibited'],
            'roles' => ['prohibited'],
            'telephone' => $proprietaire
                ? ['required', 'string', new NumeroTelephone]
                : ['nullable', 'string', new NumeroTelephone],
            'ville' => $proprietaire
                ? ['required', 'string', 'max:100']
                : ['prohibited'],
            'quartier' => $proprietaire
                ? ['nullable', 'string', 'max:150']
                : ['prohibited'],
            'adresse' => $proprietaire
                ? ['nullable', 'string', 'max:255']
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.prohibited' => 'Le rôle d’un compte ne peut pas être modifié.',
            'roles.prohibited' => 'Le rôle d’un compte ne peut pas être modifié.',
            'telephone.required' => 'Le téléphone est obligatoire pour un propriétaire : les locataires le contactent par WhatsApp.',
            'ville.required' => 'La ville est obligatoire : elle indique où récupérer le matériel.',
            'ville.prohibited' => 'La localisation ne concerne que les propriétaires.',
            'quartier.prohibited' => 'La localisation ne concerne que les propriétaires.',
            'adresse.prohibited' => 'La localisation ne concerne que les propriétaires.',
        ];
    }
}
