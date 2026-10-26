<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Depense;

/** EF5 — Gestion des dépenses : ajouter, modifier, supprimer, rechercher. */
class DepenseController extends TransactionController
{
    protected function config(): array
    {
        return [
            'modele' => Depense::class,
            'relation' => 'depenses',
            'type' => Categorie::TYPE_DEPENSE,
            'route' => 'depenses',
            'titre' => 'Dépenses',
            'libelle' => 'dépense',
            'article' => 'une',
            'nouveau' => 'Nouvelle',
            'ce' => 'cette',
            'aucun' => 'Aucune dépense enregistrée',
            'couleur' => 'danger',
            'icone' => 'bi-cash-stack',
            'msg_ajout' => 'Dépense ajoutée avec succès.',
            'msg_modif' => 'Dépense modifiée avec succès.',
            'msg_suppr' => 'Dépense supprimée avec succès.',
        ];
    }
}
