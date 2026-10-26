<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use App\Services\BudgetStats;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module 6 — Statistiques (EF9) et graphiques par période (EF10). Date figée au 15 octobre 2026. */
class StatistiquesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categorie $alimentation;

    private Categorie $transport;

    private Categorie $loyer;

    private Categorie $salaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-15 10:00:00');

        $this->user = User::factory()->create();
        $this->alimentation = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation']);
        $this->transport = Categorie::factory()->for($this->user)->create(['nom' => 'Transport']);
        $this->loyer = Categorie::factory()->for($this->user)->create(['nom' => 'Logement']);
        $this->salaire = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Salaire']);
    }

    private function depense(string $date, float $montant, ?Categorie $cat = null, ?User $user = null): Depense
    {
        $user ??= $this->user;
        $cat ??= $user->is($this->user) ? $this->alimentation : Categorie::factory()->for($user)->create();

        return Depense::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'date' => $date, 'montant' => $montant]);
    }

    private function revenu(string $date, float $montant, ?User $user = null): Revenu
    {
        $user ??= $this->user;
        $cat = $user->is($this->user) ? $this->salaire : Categorie::factory()->for($user)->revenu()->create();

        return Revenu::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'date' => $date, 'montant' => $montant]);
    }

    private function page(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->get('/statistiques'.$query);
    }

    /** @return array{repartition: array<int, array<string, mixed>>, serie: array<string, array<int, mixed>>} */
    private function graphiques(string $query = ''): array
    {
        preg_match('#<script type="application/json" id="donnees-graphiques">(.*?)</script>#s', $this->page($query)->getContent(), $m);

        return json_decode($m[1], true);
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_et_l_administrateur_a_un_403(): void
    {
        auth()->logout();
        $this->get('/statistiques')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get('/statistiques')->assertForbidden();
    }

    // ---------- Période par défaut ----------

    public function test_par_defaut_la_page_affiche_le_mois_en_cours(): void
    {
        $this->depense('2026-10-05', 5000);
        $this->depense('2026-09-05', 99000);

        $this->page()
            ->assertOk()
            ->assertSee("Statistiques d'octobre 2026")
            ->assertSee('value="2026-10"', false)
            ->assertSee('5 000 FCFA')
            ->assertDontSee('99 000 FCFA');
    }

    public function test_sans_donnee_la_page_s_affiche_sans_erreur_ni_division_par_zero(): void
    {
        $this->page()
            ->assertOk()
            ->assertSee('Aucune dépense sur cette période.')
            ->assertDontSee('Catégorie la plus coûteuse');

        $g = $this->graphiques();
        $this->assertSame([], $g['repartition']);
        $this->assertSame(array_fill(0, 6, 0), $g['serie']['revenus']);
    }

    // ---------- Répartition par catégorie (montant et %) ----------

    public function test_repartition_par_categorie_avec_montants_et_pourcentages(): void
    {
        $this->depense('2026-10-02', 5000, $this->alimentation);
        $this->depense('2026-10-03', 3000, $this->transport);
        $this->depense('2026-10-04', 2000, $this->loyer);

        $this->page()
            ->assertSeeInOrder(['Alimentation', '5 000 FCFA', '50 %', 'Transport', '3 000 FCFA', '30 %', 'Logement', '2 000 FCFA', '20 %'])
            ->assertSee('Total');

        $rep = $this->graphiques()['repartition'];
        $this->assertSame(['Alimentation', 'Transport', 'Logement'], array_column($rep, 'categorie'));
        $this->assertEqualsWithDelta(100, array_sum(array_column($rep, 'pourcentage')), 0.2);
    }

    public function test_facture_globale_est_l_addition_de_toutes_les_factures_dans_la_repartition(): void
    {
        $elec = $this->user->categories()->create(['nom' => "Facture d'électricité", 'type' => 'depense']);
        $eau = $this->user->categories()->create(['nom' => "Facture d'eau", 'type' => 'depense']);
        $internet = $this->user->categories()->create(['nom' => "Facture d'internet", 'type' => 'depense']);
        $globale = $this->user->categories()->create(['nom' => 'Facture globale', 'type' => 'depense']);

        $this->depense('2026-10-02', 12000, $elec);
        $this->depense('2026-10-03', 4000, $eau);
        $this->depense('2026-10-04', 9000, $internet);
        $this->depense('2026-10-05', 5000, $globale); // saisie directement sur la facture globale : comptée aussi
        $this->depense('2026-10-06', 20000, $this->alimentation);

        $rep = collect($this->graphiques()['repartition'])->keyBy('categorie');

        $this->assertSame(['Facture globale', 'Alimentation'], $rep->keys()->all(), 'seule la facture globale figure parmi les factures');
        $this->assertSame(30000.0, (float) $rep['Facture globale']['total'], '12 000 + 4 000 + 9 000 + 5 000');
        $this->assertSame(60.0, (float) $rep['Facture globale']['pourcentage']);
        $this->assertEqualsWithDelta(100, $rep->sum('pourcentage'), 0.2);

        $this->page()
            ->assertSee('Facture globale')->assertSee('30 000 FCFA')
            ->assertDontSee("Facture d'eau")->assertDontSee("Facture d'internet");
    }

    public function test_les_factures_ne_sont_pas_regroupees_pour_un_autre_utilisateur(): void
    {
        $autre = User::factory()->create();
        $autreElec = $autre->categories()->create(['nom' => "Facture d'électricité", 'type' => 'depense']);
        $this->depense('2026-10-02', 7000, $autreElec, $autre);
        $this->depense('2026-10-03', 1000, $this->alimentation);

        $this->assertSame(['Alimentation'], array_column($this->graphiques()['repartition'], 'categorie'));
    }

    public function test_les_pourcentages_decimaux_utilisent_la_virgule(): void
    {
        $this->depense('2026-10-02', 1000, $this->alimentation);
        $this->depense('2026-10-03', 1000, $this->transport);
        $this->depense('2026-10-04', 1000, $this->loyer);

        $this->page()->assertSee('33,3 %');
    }

    public function test_plusieurs_depenses_d_une_meme_categorie_sont_additionnees(): void
    {
        $this->depense('2026-10-02', 1000, $this->alimentation);
        $this->depense('2026-10-09', 2500, $this->alimentation);

        $rep = $this->graphiques()['repartition'];

        $this->assertCount(1, $rep);
        $this->assertSame(3500, $rep[0]['total']);
        $this->assertSame(100, $rep[0]['pourcentage']);
    }

    public function test_les_revenus_ne_figurent_pas_dans_la_repartition_des_depenses(): void
    {
        $this->revenu('2026-10-02', 250000);
        $this->depense('2026-10-03', 4000);

        $rep = $this->graphiques()['repartition'];

        $this->assertCount(1, $rep);
        $this->assertSame(4000, $rep[0]['total']);
    }

    // ---------- Catégorie la plus coûteuse ----------

    public function test_la_categorie_la_plus_couteuse_est_mise_en_avant(): void
    {
        $this->depense('2026-10-02', 1000, $this->alimentation);
        $this->depense('2026-10-03', 9000, $this->transport);

        $this->page()
            ->assertSee('Catégorie la plus coûteuse')
            ->assertSeeInOrder(['Catégorie la plus coûteuse', 'Transport', '9 000 FCFA', '90 %']);
    }

    // ---------- Moyenne par mois ----------

    public function test_la_moyenne_mensuelle_d_un_mois_est_le_total_du_mois(): void
    {
        $this->depense('2026-10-02', 4000);
        $this->depense('2026-10-20', 2000);

        $this->page('?periode=mois&mois=2026-10')->assertSee('Moyenne des dépenses par mois')->assertSee('6 000 FCFA');
    }

    public function test_la_moyenne_mensuelle_d_une_annee_porte_sur_les_mois_avec_depenses(): void
    {
        $this->depense('2026-01-10', 10000);  // janvier
        $this->depense('2026-01-20', 20000);  // janvier : 30 000 au total
        $this->depense('2026-03-05', 60000);  // mars    : 60 000 ; février sans dépense => 2 mois, pas 3

        $stats = new BudgetStats($this->user);
        $this->assertSame(45000.0, $stats->moyenneMensuelleDepenses(Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)));
        $this->page('?periode=annee&annee=2026')->assertSee('45 000 FCFA');
    }

    public function test_la_moyenne_est_zero_sans_depense(): void
    {
        $this->assertSame(0.0, (new BudgetStats($this->user))->moyenneMensuelleDepenses(Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)));
    }

    // ---------- Filtre par période : mois ----------

    public function test_filtre_par_mois(): void
    {
        $this->depense('2026-09-05', 7000);
        $this->revenu('2026-09-06', 90000);
        $this->depense('2026-10-05', 1000);

        $this->page('?periode=mois&mois=2026-09')
            ->assertSee('Statistiques de septembre 2026')
            ->assertSee('7 000 FCFA')
            ->assertSee('90 000 FCFA')
            ->assertSee('83 000 FCFA')   // solde
            ->assertDontSee('1 000 FCFA');
    }

    public function test_le_mois_choisi_inclut_son_premier_et_son_dernier_jour(): void
    {
        $this->depense('2026-08-31', 100);
        $this->depense('2026-09-01', 1000);
        $this->depense('2026-09-30', 2000);
        $this->depense('2026-10-01', 50000);

        $rep = $this->graphiques('?mois=2026-09')['repartition'];

        $this->assertSame(3000, $rep[0]['total']);
    }

    public function test_les_barres_d_un_mois_couvrent_les_6_mois_qui_se_terminent_a_ce_mois(): void
    {
        $this->depense('2026-09-10', 5000);
        $this->depense('2026-04-10', 3000);   // avril : 6 mois jusqu'à septembre = avril → septembre

        $serie = $this->graphiques('?periode=mois&mois=2026-09')['serie'];

        $this->assertCount(6, $serie['labels']);
        $this->assertSame([3000, 0, 0, 0, 0, 5000], $serie['depenses']);
    }

    public function test_une_serie_a_cheval_sur_deux_annees(): void
    {
        $this->depense('2025-11-12', 1500);
        $this->revenu('2026-02-03', 8000);

        $serie = $this->graphiques('?mois=2026-02')['serie'];

        // septembre, octobre, novembre, décembre 2025, janvier, février 2026
        $this->assertSame([0, 0, 1500, 0, 0, 0], $serie['depenses']);
        $this->assertSame([0, 0, 0, 0, 0, 8000], $serie['revenus']);
    }

    // ---------- Filtre par période : année ----------

    public function test_filtre_par_annee(): void
    {
        $this->depense('2026-01-15', 10000);
        $this->depense('2026-12-31', 5000);
        $this->depense('2025-12-31', 77000);
        $this->revenu('2026-06-01', 300000);

        $this->page('?periode=annee&annee=2026')
            ->assertSee('Statistiques de l\'année 2026')
            ->assertSee('15 000 FCFA')
            ->assertSee('300 000 FCFA')
            ->assertSee('285 000 FCFA')
            ->assertDontSee('77 000 FCFA');
    }

    public function test_les_barres_d_une_annee_ont_12_mois_de_janvier_a_decembre(): void
    {
        $this->depense('2026-01-15', 10000);
        $this->depense('2026-12-31', 5000);
        $this->revenu('2026-06-01', 300000);
        $this->depense('2025-12-31', 77000);

        $serie = $this->graphiques('?periode=annee&annee=2026')['serie'];

        $this->assertCount(12, $serie['labels']);
        $this->assertSame(10000, $serie['depenses'][0]);
        $this->assertSame(5000, $serie['depenses'][11]);
        $this->assertSame(300000, $serie['revenus'][5]);
        $this->assertSame(15000, array_sum($serie['depenses']));
    }

    public function test_une_annee_sans_donnee_affiche_des_zeros(): void
    {
        $this->page('?periode=annee&annee=2020')
            ->assertOk()
            ->assertSee('Statistiques de l\'année 2020')
            ->assertSee('Aucune dépense sur cette période.');
    }

    public function test_le_titre_elide_devant_une_voyelle_et_pas_devant_une_consonne(): void
    {
        $this->page('?mois=2026-10')->assertSee("Statistiques d'octobre 2026");   // octobre
        $this->page('?mois=2026-04')->assertSee("Statistiques d'avril 2026");     // avril
        $this->page('?mois=2026-08')->assertSee("Statistiques d'août 2026");      // août
        $this->page('?mois=2026-09')->assertSee('Statistiques de septembre 2026'); // consonne : pas d'élision
        $this->page('?periode=annee&annee=2026')->assertSee("Statistiques de l'année 2026");
    }

    public function test_le_selecteur_reflete_la_periode_choisie(): void
    {
        $this->page('?periode=annee&annee=2025')
            ->assertSee('<option value="annee" selected>', false)
            ->assertSee('value="2025"', false);
    }

    // ---------- Valeurs invalides ----------

    public function test_une_periode_invalide_retombe_sur_le_mois_en_cours_avec_un_message(): void
    {
        $this->depense('2026-10-05', 4000);

        $this->page('?periode=semaine')
            ->assertOk()
            ->assertSee('La période doit être « mois » ou « année ».')
            ->assertSee("Statistiques d'octobre 2026")
            ->assertSee('4 000 FCFA');
    }

    public function test_un_mois_invalide_retombe_sur_le_mois_en_cours(): void
    {
        foreach (['2026-13', 'abc', '2026-1', '10-2026'] as $mois) {
            $this->page('?mois='.$mois)
                ->assertOk()
                ->assertSee('Le mois doit être au format AAAA-MM.')
                ->assertSee("Statistiques d'octobre 2026");
        }
    }

    public function test_une_annee_invalide_est_refusee(): void
    {
        foreach (['abc', '1999', '2101', '20.5'] as $annee) {
            $this->page('?periode=annee&annee='.$annee)
                ->assertOk()
                ->assertSee('Statistiques de l\'année 2026'); // retombe sur l'année en cours
        }
        $this->page('?periode=annee&annee=abc')->assertSee('L\'année doit être un nombre entier.');
        $this->page('?periode=annee&annee=1999')->assertSee('L\'année doit être comprise entre 2000 et 2100.');
    }

    public function test_le_31_du_mois_un_mois_court_n_est_pas_decale(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        $this->depense('2026-02-10', 3000);

        // Sans « ! » dans le format, createFromFormat('Y-m') prendrait le jour 31 → « 31 février » = 3 mars
        $this->page('?periode=mois&mois=2026-02')
            ->assertSee('Statistiques de février 2026')
            ->assertSee('3 000 FCFA');
    }

    public function test_une_injection_dans_la_periode_est_inoffensive(): void
    {
        $this->depense('2026-10-05', 4000);

        $this->page('?periode='.urlencode("mois' OR 1=1 --").'&mois='.urlencode("2026-10'; DROP TABLE depenses;--"))
            ->assertOk()
            ->assertSee("Statistiques d'octobre 2026");
        $this->assertSame(1, Depense::count());
    }

    // ---------- Isolation ----------

    public function test_les_statistiques_n_incluent_pas_les_donnees_des_autres(): void
    {
        $autre = User::factory()->create();
        $this->depense('2026-10-05', 777000, null, $autre);
        $this->revenu('2026-10-05', 888000, $autre);
        $this->depense('2026-10-06', 1000);

        $this->page()
            ->assertDontSee('777 000')
            ->assertDontSee('888 000')
            ->assertSee('1 000 FCFA');
        $this->assertCount(1, $this->graphiques()['repartition']);
    }

    // ---------- BudgetStats (calculs seuls) ----------

    public function test_budget_stats_totaux_et_nombre_de_transactions(): void
    {
        $this->depense('2026-10-01', 1000);
        $this->depense('2026-10-31', 2000);
        $this->revenu('2026-10-15', 10000);
        $this->depense('2026-11-01', 5000);

        $stats = new BudgetStats($this->user);
        $debut = Carbon::create(2026, 10, 1);
        $fin = Carbon::create(2026, 10, 31);

        $this->assertSame(3000.0, $stats->totalDepenses($debut, $fin));
        $this->assertSame(10000.0, $stats->totalRevenus($debut, $fin));
        $this->assertSame(3, $stats->nombreTransactions($debut, $fin));
    }

    public function test_budget_stats_avec_les_centimes(): void
    {
        $this->depense('2026-10-01', 1500.50);
        $this->depense('2026-10-02', 0.25);

        $this->assertSame(1500.75, (new BudgetStats($this->user))->totalDepenses(Carbon::create(2026, 10, 1), Carbon::create(2026, 10, 31)));
    }

    public function test_budget_stats_ne_modifie_pas_les_dates_recues(): void
    {
        $debut = Carbon::create(2026, 10, 1);
        $fin = Carbon::create(2026, 10, 31);
        $dernier = Carbon::create(2026, 10, 15);

        $stats = new BudgetStats($this->user);
        $stats->totalDepenses($debut, $fin);
        $stats->serieMensuelle($dernier, 6);

        $this->assertSame('2026-10-01', $debut->toDateString());
        $this->assertSame('2026-10-31', $fin->toDateString());
        $this->assertSame('2026-10-15', $dernier->toDateString());
    }

    public function test_budget_stats_dernieres_transactions_limite_et_ordre(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->depense(sprintf('2026-10-%02d', $i), $i * 100);
        }

        $dernieres = (new BudgetStats($this->user))->dernieresTransactions(3);

        $this->assertCount(3, $dernieres);
        $this->assertSame([800.0, 700.0, 600.0], $dernieres->pluck('montant')->all());
    }

    // ---------- Courbe d'évolution du solde ----------

    public function test_evolution_du_solde_part_du_solde_accumule_avant_la_fenetre_puis_cumule_mois_par_mois(): void
    {
        $this->revenu('2026-01-10', 100000);   // avant la fenêtre de 6 mois (mai à octobre)
        $this->depense('2026-03-05', 30000);   // idem : solde de départ = 70 000
        $this->revenu('2026-08-12', 50000);    // août : +50 000
        $this->depense('2026-09-03', 20000);   // septembre : −20 000

        $evolution = (new BudgetStats($this->user))->evolutionSolde(Carbon::create(2026, 10, 15), 6);

        // mai, juin, juillet, août, septembre, octobre
        $this->assertSame([70000.0, 70000.0, 70000.0, 120000.0, 100000.0, 100000.0], $evolution['valeurs']);
        $this->assertCount(6, $evolution['labels']);
        $this->assertSame(Carbon::create(2026, 5, 1)->translatedFormat('M Y'), $evolution['labels'][0]);
        $this->assertSame(Carbon::create(2026, 10, 1)->translatedFormat('M Y'), $evolution['labels'][5]);
    }

    public function test_le_dernier_point_de_la_courbe_est_egal_au_solde_cumule(): void
    {
        $this->revenu('2026-07-01', 250000);
        $this->depense('2026-07-05', 75000);
        $this->revenu('2026-10-01', 250000);
        $this->depense('2026-10-14', 123456.5);

        $stats = new BudgetStats($this->user);
        $evolution = $stats->evolutionSolde(Carbon::create(2026, 10, 15), 6);

        $this->assertSame($stats->soldeCumule(), end($evolution['valeurs']));
    }

    public function test_evolution_du_solde_sans_aucune_donnee_est_une_ligne_a_zero(): void
    {
        $evolution = (new BudgetStats($this->user))->evolutionSolde(Carbon::create(2026, 10, 15), 6);

        $this->assertSame(array_fill(0, 6, 0.0), $evolution['valeurs']);
    }

    public function test_evolution_du_solde_ignore_les_transactions_posterieures_a_aujourd_hui(): void
    {
        $this->revenu('2026-10-10', 100000);
        $this->depense('2026-10-20', 999000);   // après le 15 octobre (date figée) : ignorée
        $this->depense('2026-11-20', 999000);

        $evolution = (new BudgetStats($this->user))->evolutionSolde(Carbon::create(2026, 10, 15), 6);

        $this->assertSame(100000.0, end($evolution['valeurs']));
    }

    public function test_evolution_du_solde_peut_etre_negative_et_garde_les_centimes(): void
    {
        $this->depense('2026-10-02', 1500.5);

        $evolution = (new BudgetStats($this->user))->evolutionSolde(Carbon::create(2026, 10, 15), 6);

        $this->assertSame(-1500.5, end($evolution['valeurs']));
    }

    public function test_evolution_du_solde_n_inclut_pas_les_donnees_des_autres(): void
    {
        $autre = User::factory()->create();
        $this->revenu('2026-10-02', 777000, $autre);
        $this->depense('2026-03-02', 555000, null, $autre);

        $evolution = (new BudgetStats($this->user))->evolutionSolde(Carbon::create(2026, 10, 15), 6);

        $this->assertSame(array_fill(0, 6, 0.0), $evolution['valeurs']);
    }

    public function test_evolution_du_solde_sur_douze_mois_reste_plate_apres_aujourd_hui(): void
    {
        $this->revenu('2026-02-10', 40000);

        $evolution = (new BudgetStats($this->user))->evolutionSolde(Carbon::create(2026, 12, 31), 12);

        $this->assertCount(12, $evolution['valeurs']);
        $this->assertSame([0.0, 40000.0], [$evolution['valeurs'][0], $evolution['valeurs'][1]]);   // janvier, février
        $this->assertSame(40000.0, $evolution['valeurs'][11]);                                      // décembre : pas de donnée future
    }

    public function test_evolution_du_solde_ne_modifie_pas_la_date_recue(): void
    {
        $mois = Carbon::create(2026, 10, 15);

        (new BudgetStats($this->user))->evolutionSolde($mois, 6);

        $this->assertSame('2026-10-15', $mois->toDateString());
    }

    public function test_budget_stats_a_des_transactions(): void
    {
        $stats = new BudgetStats($this->user);
        $this->assertFalse($stats->aDesTransactions());

        $this->revenu('2020-01-01', 100);
        $this->assertTrue($stats->aDesTransactions());
    }
}
