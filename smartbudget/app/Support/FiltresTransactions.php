<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

/**
 * Filtres de recherche combinables d'une liste de transactions (CDC §3.6, EF11) :
 * mot-clé dans la description, catégorie, intervalle de dates, montant min / max,
 * et, pour l'historique, le type (dépense ou revenu).
 * Un filtre invalide est ignoré et son erreur est renvoyée pour être affichée.
 */
final class FiltresTransactions
{
    /**
     * @return array{0: array<string, string>, 1: MessageBag}  [valeurs retenues, erreurs]
     */
    public static function lire(Request $request, bool $avecType = false): array
    {
        $regles = [
            'q' => ['nullable', 'string', 'max:100'],
            'categorie_id' => ['nullable', 'integer'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'montant_min' => ['nullable', 'numeric', 'min:0'],
            'montant_max' => ['nullable', 'numeric', 'min:0'],
        ];
        if ($avecType) {
            $regles['type'] = ['nullable', 'in:depense,revenu'];
        }

        $validateur = Validator::make($request->query(), $regles, [], [
            'q' => 'mot-clé', 'categorie_id' => 'catégorie', 'date_debut' => 'date de début', 'date_fin' => 'date de fin',
            'montant_min' => 'montant minimum', 'montant_max' => 'montant maximum', 'type' => 'type',
        ]);

        $erreurs = $validateur->errors();
        $valeurs = [];

        foreach (array_keys($regles) as $cle) {
            $valeur = $request->query($cle, '');
            $valeur = is_string($valeur) ? trim($valeur) : '';
            if ($valeur !== '' && ! $erreurs->has($cle)) {
                $valeurs[$cle] = $valeur;
            }
        }

        // Cohérence des intervalles : sinon le résultat serait toujours vide
        if (isset($valeurs['date_debut'], $valeurs['date_fin']) && $valeurs['date_debut'] > $valeurs['date_fin']) {
            $erreurs->add('date_fin', 'La date de fin doit être postérieure à la date de début.');
            unset($valeurs['date_debut'], $valeurs['date_fin']);
        }
        if (isset($valeurs['montant_min'], $valeurs['montant_max']) && (float) $valeurs['montant_min'] > (float) $valeurs['montant_max']) {
            $erreurs->add('montant_max', 'Le montant maximum doit être supérieur au montant minimum.');
            unset($valeurs['montant_min'], $valeurs['montant_max']);
        }

        return [$valeurs, $erreurs];
    }

    /**
     * Applique les filtres (hors type) à une requête sur la table $table (`depenses` ou `revenus`).
     * Les colonnes sont qualifiées par la table pour rester valides dans une jointure ou une union.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $requete
     * @param  array<string, string>  $filtres
     */
    public static function appliquer($requete, array $filtres, string $table): void
    {
        $requete
            ->when($filtres['q'] ?? null, fn ($q, $v) => $q->where("$table.description", 'like', '%'.addcslashes($v, '%_\\').'%'))
            ->when($filtres['categorie_id'] ?? null, fn ($q, $v) => $q->where("$table.categorie_id", $v))
            // Comparaisons directes sur la colonne DATE (pas de DATE(...)), pour utiliser l'index (user_id, date)
            ->when($filtres['date_debut'] ?? null, fn ($q, $v) => $q->where("$table.date", '>=', $v))
            ->when($filtres['date_fin'] ?? null, fn ($q, $v) => $q->where("$table.date", '<=', $v))
            ->when($filtres['montant_min'] ?? null, fn ($q, $v) => $q->where("$table.montant", '>=', $v))
            ->when($filtres['montant_max'] ?? null, fn ($q, $v) => $q->where("$table.montant", '<=', $v));
    }
}
