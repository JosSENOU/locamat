<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockedAccountSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'message.required' => 'Le message est obligatoire.',
            'message.min' => 'Le message doit contenir au moins 10 caractères.',
            'message.max' => 'Le message ne peut pas dépasser 1 000 caractères.',
        ];
    }
}
