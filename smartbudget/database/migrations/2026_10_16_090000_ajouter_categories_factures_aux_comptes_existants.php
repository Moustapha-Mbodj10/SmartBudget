<?php

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * La catégorie par défaut « Factures » a été remplacée, pour les nouveaux comptes, par quatre catégories
 * (électricité, eau, internet, globale). Cette migration les ajoute aux comptes utilisateurs déjà créés.
 *
 * Données seulement : « Factures » et toutes les transactions existantes ne sont pas touchées, et seules ces quatre
 * catégories sont ajoutées (une catégorie par défaut supprimée volontairement par une personne n'est pas recréée).
 * Rejouable sans doublon grâce à firstOrCreate. Les comptes sans catégorie (administrateur) sont ignorés.
 */
return new class extends Migration
{
    private const NOUVELLES = [
        ["Facture d'électricité", null],
        ["Facture d'eau", null],
        ["Facture d'internet", null],
        ['Facture globale', 'Eau, électricité et internet réunis'],
    ];

    public function up(): void
    {
        User::where('role', 'utilisateur')->each(function (User $user) {
            foreach (self::NOUVELLES as [$nom, $description]) {
                $user->categories()->firstOrCreate(
                    ['nom' => $nom, 'type' => Categorie::TYPE_DEPENSE],
                    ['description' => $description],
                );
            }
        });
    }

    public function down(): void
    {
        // Retour arrière volontairement limité aux catégories encore inutilisées : on ne supprime jamais une catégorie qui porte des transactions.
        foreach (self::NOUVELLES as [$nom]) {
            Categorie::where('nom', $nom)->where('type', Categorie::TYPE_DEPENSE)
                ->whereDoesntHave('depenses')->delete();
        }
    }
};
