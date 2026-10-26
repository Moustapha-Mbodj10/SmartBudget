<?php

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supprime la catégorie « Factures » de tous les comptes : « Facture globale » prend sa place.
 *
 * Aucune transaction n'est perdue : les dépenses classées dans « Factures » sont d'abord déplacées vers
 * « Facture globale » (créée au besoin), puis « Factures » est supprimée. Le tout dans une transaction,
 * rejouable sans effet (rien à faire quand « Factures » n'existe plus).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            Categorie::where('nom', 'Factures')->where('type', Categorie::TYPE_DEPENSE)->get()->each(function (Categorie $ancienne) {
                $globale = $ancienne->user->categories()->firstOrCreate(
                    ['nom' => 'Facture globale', 'type' => Categorie::TYPE_DEPENSE],
                    ['description' => 'Eau, électricité et internet réunis'],
                );

                $ancienne->depenses()->update(['categorie_id' => $globale->id]);
                $ancienne->delete();
            });
        });
    }

    public function down(): void
    {
        // Les dépenses déplacées ne peuvent pas être retrouvées : on se contente de recréer la catégorie vide.
        User::where('role', 'utilisateur')->each(fn (User $user) => $user->categories()->firstOrCreate(
            ['nom' => 'Factures', 'type' => Categorie::TYPE_DEPENSE],
            ['description' => 'Eau, électricité, internet'],
        ));
    }
};
