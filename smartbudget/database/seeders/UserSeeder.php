<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration (CDC §6.5) : 1 administrateur et 2 utilisateurs.
 * DONNÉES DE TEST — à ne pas utiliser en production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = [
            ['nom' => 'Admin', 'prenom' => 'SmartBudget', 'email' => 'admin@smartbudget.sn', 'password' => 'Admin1234', 'role' => User::ROLE_ADMIN],
            ['nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'awa@smartbudget.sn', 'password' => 'Password123', 'role' => User::ROLE_UTILISATEUR],
            ['nom' => 'Ndiaye', 'prenom' => 'Moussa', 'email' => 'moussa@smartbudget.sn', 'password' => 'Password123', 'role' => User::ROLE_UTILISATEUR],
        ];

        foreach ($comptes as $compte) {
            $user = User::firstOrNew(['email' => $compte['email']]);
            $user->fill(['nom' => $compte['nom'], 'prenom' => $compte['prenom'], 'password' => $compte['password']]);
            $user->role = $compte['role'];
            $user->statut = User::STATUT_ACTIF;
            $user->save();

            // Catégories par défaut pour chaque utilisateur (l'administrateur n'a pas d'espace budget)
            if (! $user->isAdmin()) {
                Categorie::creerParDefaut($user);
            }
        }
    }
}
