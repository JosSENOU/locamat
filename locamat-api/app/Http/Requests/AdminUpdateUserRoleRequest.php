<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required_without:roles', 'prohibits:roles', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_PROPRIETAIRE,
                User::ROLE_LOCATAIRE,
            ])],
            'roles' => ['required_without:role', 'array', 'min:1', 'prohibits:role'],
            'roles.*' => ['required', 'distinct', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_PROPRIETAIRE,
                User::ROLE_LOCATAIRE,
            ])],
        ];
    }
}
