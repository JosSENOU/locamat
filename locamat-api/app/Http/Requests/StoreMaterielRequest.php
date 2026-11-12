<?php

namespace App\Http\Requests;

use App\Models\Materiel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterielRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $etats = $this->input('etats_exemplaires');

        if (is_string($etats)) {
            $decoded = json_decode($etats, true);

            if (is_array($decoded)) {
                $this->merge(['etats_exemplaires' => $decoded]);
            }
        }

        $materiel = $this->route('materiel');

        if ($this->exists('etats_exemplaires') || ! ($materiel instanceof Materiel)) {
            return;
        }

        $quantite = $this->input('quantite', $materiel->quantite);
        $quantite = is_numeric($quantite) ? max(1, (int) $quantite) : 1;
        $etatsExistants = $materiel->etats_exemplaires ?? [];
        $etatParDefaut = $materiel->etat;

        $this->merge([
            'etats_exemplaires' => array_slice(
                array_pad($etatsExistants, $quantite, $etatParDefaut),
                0,
                $quantite,
            ),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', 'image', 'mimes:jpg,png,webp', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $quantite = $this->input('quantite', $this->route('materiel')?->quantite ?? 1);
            $etats = $this->input('etats_exemplaires');

            if (
                is_numeric($quantite)
                && (int) $quantite > 0
                && is_array($etats)
                && count($etats) !== (int) $quantite
            ) {
                $validator->errors()->add(
                    'etats_exemplaires',
                    'Renseignez l’état de chacun des exemplaires indiqués en quantité.',
                );
            }
        });
    }
}
