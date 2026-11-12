<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'Ce nom de catégorie existe déjà (elle est peut-être dans la corbeille du super-administrateur).'];
    }

    public function rules(): array
    {
        $uniqueName = Rule::unique('categories', 'name');
        $category = $this->route('category');

        if ($category instanceof Category) {
            $uniqueName->ignore($category);
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueName],
        ];
    }
}
