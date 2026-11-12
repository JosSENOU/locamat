<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Rules\NumeroTelephone;
use Illuminate\Foundation\Http\FormRequest;

class BecomeOwnerRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'telephone' => ['required', 'string', new NumeroTelephone],
            'ville' => ['required', 'string', 'max:100'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:150'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
