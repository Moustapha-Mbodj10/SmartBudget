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
 * 22 comptes utilisateurs supplémentaires, avec environ 6 mois de revenus et de dépenses chacun.
 * DONNÉES DE TEST : mot de passe « Password123 » (comme les autres comptes de démonstration) et chaque description
 * commence par « [TEST] ». Les dates ne sont jamais dans le futur et le résultat est reproductible (aucun hasard).
 *
 * Rejouable : un compte déjà présent n'est pas recréé (ni son mot de passe modifié) ; seules ses lignes « [TEST] »
 * sont remplacées. Volontairement absent de DatabaseSeeder : à lancer à la demande avec
 *     php artisan db:seed --class=UtilisateursSupplementairesSeeder
 */
class UtilisateursSupplementairesSeeder extends Seeder
{
    public const MARQUE = '[TEST]';

    public const NB_MOIS = 6;

    /** [prénom, nom, salaire mensuel, loyer, autre revenu (description, montant) ou null, profil de dépenses] */
    private function profils(): array
    {
        return [
            ['Fatou', 'Sow', 285000, 90000, null, 'famille'],
            ['Ibrahima', 'Fall', 420000, 140000, ['Prime de rendement', 60000], 'celibataire'],
            ['Aminata', 'Ba', 180000, 55000, ['Vente de pagnes', 35000], 'commerce'],
            ['Cheikh', 'Gueye', 350000, 110000, null, 'famille'],
            ['Mariama', 'Diallo', 150000, 45000, null, 'etudiant'],
            ['Ousmane', 'Sarr', 520000, 175000, ['Location de studio', 80000], 'celibataire'],
            ['Khady', 'Ndoye', 240000, 75000, null, 'famille'],
            ['Modou', 'Faye', 200000, 60000, ['Petit commerce', 45000], 'commerce'],
            ['Astou', 'Mbaye', 310000, 100000, null, 'celibataire'],
            ['Abdoulaye', 'Seck', 275000, 85000, null, 'famille'],
            ['Ndeye', 'Cisse', 130000, 40000, null, 'etudiant'],
            ['Pape', 'Thiaw', 460000, 150000, ['Mission ponctuelle', 70000], 'celibataire'],
            ['Seynabou', 'Kane', 225000, 70000, null, 'famille'],
            ['Alioune', 'Diouf', 390000, 120000, null, 'celibataire'],
            ['Rokhaya', 'Lo', 165000, 50000, ['Vente en ligne', 40000], 'commerce'],
            ['Mamadou', 'Camara', 330000, 105000, null, 'famille'],
            ['Dieynaba', 'Samb', 195000, 60000, null, 'celibataire'],
            ['Babacar', 'Niang', 580000, 200000, ['Cours particuliers', 55000], 'famille'],
            ['Coumba', 'Dione', 140000, 42000, null, 'etudiant'],
            ['Serigne', 'Toure', 255000, 80000, ['Transport de marchandises', 50000], 'commerce'],
            ['Awa', 'Gning', 370000, 115000, null, 'celibataire'],
            ['Lamine', 'Bousso', 300000, 95000, null, 'famille'],
        ];
    }

    /** Dépenses mensuelles selon le profil : [jour, catégorie, montant, description, mois concernés (null = tous)]. */
    private function depenses(string $profil, int $salaire): array
    {
        $part = fn (float $p) => (int) (round($salaire * $p / 500) * 500); // arrondi à 500 FCFA

        $communes = [
            [3, 'Alimentation', $part(0.07), 'Courses au marché', null],
            [10, 'Alimentation', $part(0.08), 'Courses supermarché', null],
            [17, 'Alimentation', $part(0.06), 'Courses au marché', null],
            [24, 'Alimentation', $part(0.07), 'Courses supermarché', null],
            [7, 'Transport', $part(0.03), 'Transport en commun', null],
            [21, 'Transport', $part(0.025), 'Taxi', null],
            [12, 'Facture d\'électricité', $part(0.06), 'Facture électricité', null],
            [13, 'Facture d\'eau', $part(0.02), 'Facture eau', null],
            [14, 'Facture d\'internet', $part(0.045), 'Abonnement internet', null],
        ];

        $specifiques = match ($profil) {
            'famille' => [
                [2, 'Éducation', $part(0.08), 'Frais de scolarité', fn ($m) => $m % 2 === 1],
                [9, 'Santé', $part(0.03), 'Pharmacie', fn ($m) => $m % 3 === 1],
                [18, 'Loisirs', $part(0.03), 'Sortie en famille', null],
            ],
            'commerce' => [
                [9, 'Santé', $part(0.02), 'Consultation', fn ($m) => $m === 2],
                [19, 'Loisirs', $part(0.025), 'Sorties', fn ($m) => $m % 2 === 0],
            ],
            'etudiant' => [
                [2, 'Éducation', $part(0.12), 'Frais d\'inscription et livres', fn ($m) => $m % 3 === 0],
                [18, 'Loisirs', $part(0.04), 'Cinéma et sorties', null],
            ],
            default => [ // célibataire
                [9, 'Santé', $part(0.02), 'Pharmacie', fn ($m) => $m % 3 === 1],
                [18, 'Loisirs', $part(0.05), 'Restaurant et sorties', null],
                [26, 'Loisirs', $part(0.03), 'Abonnements', fn ($m) => $m % 2 === 0],
            ],
        };

        return array_merge($communes, $specifiques);
    }

    public function run(): void
    {
        DB::transaction(function () {
            $aujourdhui = Carbon::today();

            foreach ($this->profils() as $i => [$prenom, $nom, $salaire, $loyer, $autreRevenu, $profil]) {
                $email = $this->email($prenom, $nom);

                $user = User::firstOrNew(['email' => $email]);
                if (! $user->exists) {
                    $user->fill(['nom' => $nom, 'prenom' => $prenom, 'password' => 'Password123']);
                    $user->role = User::ROLE_UTILISATEUR;
                    $user->statut = User::STATUT_ACTIF;
                    $user->save();
                }
                Categorie::creerParDefaut($user);

                // Rejouable : on remplace uniquement les lignes de test de ce compte
                $user->depenses()->where('description', 'like', self::MARQUE.'%')->delete();
                $user->revenus()->where('description', 'like', self::MARQUE.'%')->delete();

                $categories = $user->categories()->get()->keyBy(fn ($c) => $c->type.'|'.$c->nom);

                $revenus = [[1 + $i % 5, 'Salaire', $salaire, 'Salaire', null]];
                if ($autreRevenu) {
                    $revenus[] = [20, $profil === 'commerce' ? 'Activité commerciale' : 'Autres revenus', $autreRevenu[1], $autreRevenu[0], fn ($m) => $m % 2 === 0];
                }
                $depenses = array_merge([[5, 'Logement', $loyer, 'Loyer', null]], $this->depenses($profil, $salaire));

                foreach (range(0, self::NB_MOIS - 1) as $indice) {
                    $mois = $aujourdhui->copy()->startOfMonth()->subMonths($indice);

                    foreach (['revenu' => $revenus, 'depense' => $depenses] as $type => $lignes) {
                        foreach ($lignes as $j => [$jour, $nomCategorie, $montant, $description, $quand]) {
                            if ($quand !== null && ! $quand($indice)) {
                                continue;
                            }
                            $date = $mois->copy()->day(min($jour, $mois->daysInMonth));
                            if ($date->gt($aujourdhui)) {
                                continue; // jamais de date dans le futur
                            }

                            // Petite variation déterministe d'un mois à l'autre (le salaire et le loyer restent fixes)
                            $fixe = in_array($nomCategorie, ['Salaire', 'Logement'], true);
                            $variation = $fixe ? 0 : (($indice * 7 + $j * 3 + $i) % 5) * 500;

                            $donnees = [
                                'user_id' => $user->id,
                                'categorie_id' => $categories[($type === 'revenu' ? Categorie::TYPE_REVENU : Categorie::TYPE_DEPENSE).'|'.$nomCategorie]->id,
                                'montant' => $montant + $variation,
                                'date' => $date->toDateString(),
                                'description' => self::MARQUE.' '.$description,
                            ];
                            $type === 'revenu' ? Revenu::create($donnees) : Depense::create($donnees);
                        }
                    }
                }
            }
        });
    }

    /** Adresses des 22 comptes, dans l'ordre de création (utilisé par la commande demo:presence). */
    public static function emails(): array
    {
        return array_map(fn (array $p) => self::email($p[0], $p[1]), (new self)->profils());
    }

    /** Adresse du compte : prenom.nom@smartbudget.sn, sans accent ni espace. */
    public static function email(string $prenom, string $nom): string
    {
        $simple = fn (string $s) => strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $s)));

        return $simple($prenom).'.'.$simple($nom).'@smartbudget.sn';
    }
}
