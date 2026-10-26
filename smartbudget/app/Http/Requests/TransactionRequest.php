<?php

namespace App\Http\Requests;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une dépense ou d'un revenu (CDC §7.5). Le type (dépense / revenu) est déduit de la route :
 * la catégorie choisie doit appartenir à l'utilisateur ET être du même type que la transaction.
 */
class TransactionRequest extends FormRequest
{
    public function estUnRevenu(): bool
    {
        return $this->routeIs('revenus.*');
    }

    public function typeCategorie(): string
    {
        return $this->estUnRevenu() ? Categorie::TYPE_REVENU : Categorie::TYPE_DEPENSE;
    }

    /**
     * L'autorisation passe avant la validation : la transaction d'un autre utilisateur donne 403.
     * Si elle n'existe pas, on laisse le contrôleur répondre 404.
     */
    public function authorize(): bool
    {
        $id = $this->route('transaction');

        if ($id === null) {
            return true; // création
        }

        $classe = $this->estUnRevenu() ? Revenu::class : Depense::class;
        $transaction = $classe::find($id);

        return $transaction ? $this->user()->can('update', $transaction) : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'categorie_id' => [
                'required',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('type', $this->typeCategorie()),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'montant.min' => 'Le montant doit être strictement positif.',
            'categorie_id.required' => 'Veuillez choisir une catégorie.',
            'categorie_id.exists' => 'Veuillez choisir une de vos catégories de ce type.',
        ];
    }
}
