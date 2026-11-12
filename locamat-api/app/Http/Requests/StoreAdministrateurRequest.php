<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Rules\NumeroTelephone;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdministrateurRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword(12)],
            'telephone' => ['sometimes', 'nullable', 'string', new NumeroTelephone],
        ];
    }
}
