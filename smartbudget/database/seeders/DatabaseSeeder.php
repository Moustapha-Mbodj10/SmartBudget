<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,        // 1 administrateur, 2 utilisateurs, catégories par défaut
            TransactionSeeder::class, // ~6 mois de transactions fictives « [TEST] »
        ]);
    }
}
