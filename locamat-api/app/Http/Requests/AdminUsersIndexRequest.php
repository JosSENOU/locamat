<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUsersIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_PROPRIETAIRE,
                User::ROLE_LOCATAIRE,
            ])],
            'is_active' => ['sometimes', Rule::in(['0', '1'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
