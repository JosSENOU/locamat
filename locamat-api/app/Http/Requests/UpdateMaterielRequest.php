<?php

namespace App\Http\Requests;

use App\Models\Materiel;
use Illuminate\Validation\Rule;

class UpdateMaterielRequest extends StoreMaterielRequest
{
    public function authorize(): bool
    {
        $materiel = $this->route('materiel');

        return $materiel instanceof Materiel
            && $this->user()?->getAuthIdentifier() === $materiel->user_id;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_id' => ['required_without:categorie', 'nullable', 'integer', Rule::exists('categories', 'id')],
            'categorie' => ['required_without:categorie_id', 'string', 'max:255', 'prohibits:categorie_id'],
            'description' => ['nullable', 'string'],
            'prix_journalier' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'etat' => ['sometimes', 'string', Rule::in(['neuf', 'bon', 'usé'])],
            'quantite' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'etats_exemplaires' => ['required', 'array', 'min:1', 'max:10000'],
            'etats_exemplaires.*' => ['required', 'string', Rule::in(['neuf', 'bon', 'usé'])],
            'disponible' => ['sometimes', 'boolean'],
            'photo' => ['prohibited'],
            'photos' => ['sometimes', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', 'image', 'mimes:jpg,png,webp', 'max:2048'],
        ];
    }
}
