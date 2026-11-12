<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorbeilleIndexRequest extends FormRequest
{
    public const TYPE_UTILISATEURS = 'utilisateurs';

    public const TYPE_MATERIELS = 'materiels';

    public const TYPE_CATEGORIES = 'categories';

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
            'type' => ['sometimes', Rule::in([self::TYPE_UTILISATEURS, self::TYPE_MATERIELS, self::TYPE_CATEGORIES])],
            'q' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
