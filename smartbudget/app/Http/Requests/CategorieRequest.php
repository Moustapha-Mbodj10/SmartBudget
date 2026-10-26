<?php

namespace App\Http\Requests;

use App\Models\Categorie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une catégorie (CDC §7.5) : nom unique par utilisateur et par type.
 * À la modification, le type est celui de la catégorie existante : il ne se change pas
 * (cela rendrait incohérentes les transactions déjà classées dedans).
 */
class CategorieRequest extends FormRequest
{
    /** L'autorisation passe avant la validation : la catégorie d'un autre utilisateur donne 403. */
    public function authorize(): bool
    {
        $categorie = $this->route('categorie');

        return $categorie ? $this->user()->can('update', $categorie) : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Categorie|null $categorie */
        $categorie = $this->route('categorie');

        $type = $categorie?->type ?? $this->input('type');

        $regles = [
            'nom' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'nom')
                    ->where('user_id', $this->user()->id)
                    ->where('type', $type)
                    ->ignore($categorie?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        if (! $categorie) {
            $regles['type'] = ['required', Rule::in([Categorie::TYPE_DEPENSE, Categorie::TYPE_REVENU])];
        }

        return $regles;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.unique' => 'Vous avez déjà une catégorie portant ce nom pour ce type.',
        ];
    }
}
