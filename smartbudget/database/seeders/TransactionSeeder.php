<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Transactions fictives des comptes de démonstration (CDC §6.5) : environ 6 mois de salaire, loyer, courses...
 * DONNÉES DE TEST : chaque description commence par « [TEST] » pour qu'on ne puisse pas les confondre avec
 * de vraies données. Les dates sont calculées à partir d'aujourd'hui et ne sont jamais dans le futur.
 * Le seeder est rejouable : il remplace ses propres lignes « [TEST] » sans toucher aux autres.
 */
class TransactionSeeder extends Seeder
{
    public const MARQUE = '[TEST]';

    public const NB_MOIS = 6;

    /**
     * Modèle mensuel de chaque compte : [type, jour du mois, catégorie, montant, description, mois concernés].
     * « mois concernés » : null = tous les mois, sinon test sur l'indice du mois (0 = mois en cours, 1 = le précédent...).
     */
    private function modeles(): array
    {
        return [
            'awa@smartbudget.sn' => [
                ['revenu', 1, 'Salaire', 250000, 'Salaire', null],
                ['revenu', 20, 'Activité commerciale', 45000, 'Vente de tissus', fn ($m) => $m % 2 === 0],
                ['revenu', 12, 'Autres revenus', 25000, 'Remboursement', fn ($m) => $m === 3],
                ['depense', 5, 'Logement', 75000, 'Loyer', null],
                ['depense', 3, 'Alimentation', 18000, 'Courses au marché', null],
                ['depense', 10, 'Alimentation', 22500, 'Courses supermarché', null],
                ['depense', 17, 'Alimentation', 16000, 'Courses au marché', null],
                ['depense', 24, 'Alimentation', 21000, 'Courses supermarché', null],
                ['depense', 7, 'Transport', 5000, 'Transport en commun', null],
                ['depense', 21, 'Transport', 6500, 'Taxi', null],
                ['depense', 12, "Facture d'électricité", 22000, 'Facture électricité', null],
                ['depense', 14, "Facture d'internet", 15000, 'Abonnement internet', null],
                ['depense', 18, 'Loisirs', 12000, 'Sortie au restaurant', null],
                ['depense', 9, 'Santé', 8500, 'Pharmacie', fn ($m) => $m % 3 === 1],
                ['depense', 2, 'Éducation', 20000, 'Frais de scolarité', fn ($m) => $m % 2 === 1],
            ],
            'moussa@smartbudget.sn' => [
                ['revenu', 2, 'Salaire', 180000, 'Salaire', null],
                ['revenu', 15, 'Activité commerciale', 30000, 'Petite vente', fn ($m) => $m % 3 === 0],
                ['depense', 4, 'Logement', 60000, 'Loyer', null],
                ['depense', 6, 'Alimentation', 24000, 'Courses', null],
                ['depense', 19, 'Alimentation', 19500, 'Courses', null],
                ['depense', 8, 'Transport', 9000, 'Transport en commun', null],
                ['depense', 13, 'Facture globale', 18000, 'Facture électricité et eau', null],
                ['depense', 22, 'Loisirs', 7500, 'Cinéma et sorties', fn ($m) => $m % 2 === 0],
                ['depense', 11, 'Santé', 6000, 'Consultation', fn ($m) => $m === 2],
            ],
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            $aujourdhui = Carbon::today();

            foreach ($this->modeles() as $email => $lignes) {
                $user = User::where('email', $email)->first();
                if (! $user) {
                    continue; // UserSeeder n'a pas créé ce compte
                }

                // Rejouable : on retire d'abord les anciennes lignes de test de ce compte
                $user->depenses()->where('description', 'like', self::MARQUE.'%')->delete();
                $user->revenus()->where('description', 'like', self::MARQUE.'%')->delete();

                $categories = $user->categories()->get()->keyBy(fn ($c) => $c->type.'|'.$c->nom);

                foreach (range(0, self::NB_MOIS - 1) as $indice) {
                    $mois = $aujourdhui->copy()->startOfMonth()->subMonths($indice);

                    foreach ($lignes as [$type, $jour, $nomCategorie, $montant, $description, $quand]) {
                        if ($quand !== null && ! $quand($indice)) {
                            continue;
                        }

                        $date = $mois->copy()->day(min($jour, $mois->daysInMonth));
                        if ($date->gt($aujourdhui)) {
                            continue; // jamais de date dans le futur
                        }

                        $categorie = $categories[($type === 'revenu' ? Categorie::TYPE_REVENU : Categorie::TYPE_DEPENSE).'|'.$nomCategorie];

                        // Petite variation déterministe d'un mois à l'autre (aucun hasard : résultat reproductible)
                        $variation = $nomCategorie === 'Salaire' || $nomCategorie === 'Logement' ? 0 : (($indice * 7 + $jour) % 5) * 500;

                        $donnees = [
                            'user_id' => $user->id,
                            'categorie_id' => $categorie->id,
                            'montant' => $montant + $variation,
                            'date' => $date->toDateString(),
                            'description' => self::MARQUE.' '.$description,
                        ];

                        $type === 'revenu' ? Revenu::create($donnees) : Depense::create($donnees);
                    }
                }
            }
        });
    }
}
