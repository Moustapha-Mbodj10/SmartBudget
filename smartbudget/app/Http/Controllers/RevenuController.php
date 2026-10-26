<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Revenu;

/** EF4 — Gestion des revenus : mêmes fonctions et règles que les dépenses, avec des catégories de type « revenu ». */
class RevenuController extends TransactionController
{
    protected function config(): array
    {
        return [
            'modele' => Revenu::class,
            'relation' => 'revenus',
            'type' => Categorie::TYPE_REVENU,
            'route' => 'revenus',
            'titre' => 'Revenus',
            'libelle' => 'revenu',
            'article' => 'un',
            'nouveau' => 'Nouveau',
            'ce' => 'ce',
            'aucun' => 'Aucun revenu enregistré',
            'couleur' => 'success',
            'icone' => 'bi-wallet2',
            'msg_ajout' => 'Revenu ajouté avec succès.',
            'msg_modif' => 'Revenu modifié avec succès.',
            'msg_suppr' => 'Revenu supprimé avec succès.',
        ];
    }
}
