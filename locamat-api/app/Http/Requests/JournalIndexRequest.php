<?php

namespace App\Http\Requests;

use App\Models\AdminAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalIndexRequest extends FormRequest
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
            'action' => ['sometimes', Rule::in(AdminAction::actions())],
            'utilisateur' => ['sometimes', 'integer', 'min:1'],
            'du' => ['sometimes', 'date_format:Y-m-d'],
            'au' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:du'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
