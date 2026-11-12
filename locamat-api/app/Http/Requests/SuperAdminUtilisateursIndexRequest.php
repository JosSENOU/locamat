<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuperAdminUtilisateursIndexRequest extends FormRequest
{
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
            'q' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in([User::ROLE_LOCATAIRE, User::ROLE_PROPRIETAIRE, User::ROLE_ADMIN])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
