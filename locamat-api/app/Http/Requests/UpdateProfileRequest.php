<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'photo' => $this->user()?->hasRole('admin')
                ? ['prohibited']
                : ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => $this->user()?->hasRole('admin')
                ? ['prohibited']
                : ['sometimes', 'boolean'],
        ];
    }
}
