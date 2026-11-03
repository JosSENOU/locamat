<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminMaterielsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'disponible' => ['sometimes', Rule::in(['0', '1'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
