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
            'role' => ['required', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_PROPRIETAIRE,
                User::ROLE_LOCATAIRE,
            ])],
        ];
    }
}
