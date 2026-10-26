<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module 6 — Tableau de bord (EF8) et graphiques du mois (EF10). Date figée au 15 octobre 2026. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categorie $alimentation;

    private Categorie $transport;

    private Categorie $salaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-15 10:00:00');

        $this->user = User::factory()->create(['prenom' => 'Awa']);
        $this->alimentation = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation']);
        $this->transport = Categorie::factory()->for($this->user)->create(['nom' => 'Transport']);
        $this->salaire = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Salaire']);
    }

    private function depense(string $date, float $montant, ?Categorie $cat = null, ?User $user = null, string $description = 'Dépense'): Depense
    {
        $user ??= $this->user;
        $cat ??= $user->is($this->user) ? $this->alimentation : Categorie::factory()->for($user)->create();

        return Depense::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'date' => $date, 'montant' => $montant, 'description' => $description]);
    }

    private function revenu(string $date, float $montant, ?User $user = null, string $description = 'Revenu'): Revenu
    {
        $user ??= $this->user;
        $cat = $user->is($this->user) ? $this->salaire : Categorie::factory()->for($user)->revenu()->create();

        return Revenu::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'date' => $date, 'montant' => $montant, 'description' => $description]);
    }

    /** @return array{repartition: array<int, array<string, mixed>>, serie: array<string, array<int, mixed>>} */
    private function graphiques(string $url = '/dashboard'): array
    {
        $html = $this->actingAs($this->user)->get($url)->getContent();
        preg_match('#<script type="application/json" id="donnees-graphiques">(.*?)</script>#s', $html, $m);

        return json_decode($m[1], true);
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/statistiques')->assertRedirect(route('login'));
    }

    public function test_l_administrateur_n_a_pas_acces_au_tableau_de_bord(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertForbidden();
    }

    public function test_un_utilisateur_suspendu_est_deconnecte(): void
    {
        $this->actingAs(User::factory()->suspendu()->create())->get('/dashboard')->assertRedirect(route('login'));
    }

    // ---------- Données absentes ----------

    public function test_sans_aucune_transaction_le_tableau_de_bord_est_a_zero_avec_un_message(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Vous n\'avez encore saisi aucune transaction.', false)
            ->assertSee(route('depenses.create'), false)
            ->assertSee(route('revenus.create'), false)
            ->assertSee('Aucune transaction pour le moment.')
            ->assertSee('Aucune dépense ce mois-ci.');
    }

    public function test_sans_transaction_les_quatre_cartes_valent_zero(): void
    {
        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertSame(4, substr_count($html, '0 FCFA'), 'revenus, dépenses, solde du mois et solde cumulé à 0 FCFA');
        $this->assertStringContainsString('Solde cumulé : 0 FCFA', $html);
        $this->assertMatchesRegularExpression('/Transactions du mois<\/div>\s*<div[^>]*>0</', $html);
    }

    public function test_sans_transaction_les_graphiques_sont_vides_mais_la_serie_a_6_mois_a_zero(): void
    {
        $g = $this->graphiques();

        $this->assertSame([], $g['repartition']);
        $this->assertCount(6, $g['serie']['labels']);
        $this->assertSame([0, 0, 0, 0, 0, 0], $g['serie']['revenus']);
        $this->assertSame([0, 0, 0, 0, 0, 0], $g['serie']['depenses']);
    }

    public function test_le_message_d_invitation_disparait_des_qu_il_existe_une_transaction_meme_ancienne(): void
    {
        $this->depense('2025-01-10', 1000);

        $this->actingAs($this->user)->get('/dashboard')->assertDontSee('Vous n\'avez encore saisi aucune transaction.', false);
    }

    // ---------- Cartes ----------

    public function test_les_cartes_affichent_revenus_depenses_solde_et_nombre_de_transactions_du_mois(): void
    {
        $this->revenu('2026-10-05', 250000);
        $this->depense('2026-10-06', 75000);
        $this->depense('2026-10-07', 5000);

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertStringContainsString('250 000 FCFA', $html);
        $this->assertStringContainsString('80 000 FCFA', $html);
        $this->assertStringContainsString('170 000 FCFA', $html);
        $this->assertMatchesRegularExpression('/Transactions du mois<\/div>\s*<div[^>]*>3</', $html);
    }

    /** T4 (seconde moitié) : le solde du tableau de bord diminue de 5 000 FCFA. */
    public function test_t4_une_depense_de_5000_fcfa_diminue_le_solde_de_5000(): void
    {
        $this->revenu('2026-10-01', 250000);
        $this->actingAs($this->user)->get('/dashboard')->assertSee('250 000 FCFA');

        $this->actingAs($this->user)->post('/depenses', [
            'montant' => '5000', 'date' => '2026-10-15', 'categorie_id' => $this->alimentation->id, 'description' => 'Marché',
        ])->assertRedirect(route('depenses.index'));

        $this->actingAs($this->user)->get('/dashboard')
            ->assertSee('245 000 FCFA')   // solde : 250 000 − 5 000
            ->assertSee('5 000 FCFA');    // dépenses du mois
    }

    public function test_seul_le_mois_en_cours_est_compte_bornes_incluses(): void
    {
        $this->depense('2026-09-30', 111000);  // veille du mois : exclue
        $this->depense('2026-10-01', 1000);    // premier jour : incluse
        $this->depense('2026-10-31', 2000);    // dernier jour : incluse
        $this->depense('2026-11-01', 999000);  // lendemain : exclue

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        // On ne regarde que les cartes : la liste des dernières transactions, elle, couvre tous les mois
        $cartes = substr($html, strpos($html, 'Revenus du mois'), strpos($html, 'Dernières transactions') - strpos($html, 'Revenus du mois'));

        $this->assertStringContainsString('3 000 FCFA', $cartes);
        $this->assertStringNotContainsString('111 000', $cartes);
        $this->assertStringNotContainsString('999 000', $cartes);
        $this->assertMatchesRegularExpression('/Transactions du mois<\/div>\s*<div[^>]*>2</', $cartes);

        // Le camembert et les barres respectent aussi les bornes
        $g = $this->graphiques();
        $this->assertSame(3000, $g['repartition'][0]['total']);
        $this->assertSame(3000, $g['serie']['depenses'][5]);
        $this->assertSame(111000, $g['serie']['depenses'][4]); // septembre
    }

    public function test_les_dernieres_transactions_couvrent_tous_les_mois(): void
    {
        $this->depense('2026-09-30', 111000, null, null, 'VEILLE');
        $this->depense('2026-11-01', 999000, null, null, 'LENDEMAIN');

        $this->actingAs($this->user)->get('/dashboard')->assertSee('VEILLE')->assertSee('LENDEMAIN');
    }

    public function test_un_solde_negatif_est_affiche_en_rouge_et_un_solde_positif_en_vert(): void
    {
        $this->depense('2026-10-05', 5000);
        $negatif = $this->actingAs($this->user)->get('/dashboard')->getContent();
        $this->assertMatchesRegularExpression('/Solde du mois.*?sb-negative/s', $negatif);
        $this->assertStringContainsString('-5 000 FCFA', $negatif);

        $this->revenu('2026-10-06', 20000);
        $positif = $this->actingAs($this->user)->get('/dashboard')->getContent();
        $this->assertMatchesRegularExpression('/Solde du mois.*?sb-positive/s', $positif);
        $this->assertStringContainsString('15 000 FCFA', $positif);
    }

    public function test_les_decimales_sont_conservees(): void
    {
        $this->depense('2026-10-05', 1500.5);

        $this->actingAs($this->user)->get('/dashboard')->assertSee('1 500,50 FCFA');
    }

    public function test_les_donnees_des_autres_utilisateurs_sont_ignorees(): void
    {
        $autre = User::factory()->create();
        $this->depense('2026-10-05', 777000, null, $autre);
        $this->revenu('2026-10-05', 888000, $autre);
        $this->depense('2026-10-05', 1000);

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('777 000', $html);
        $this->assertStringNotContainsString('888 000', $html);
        $this->assertMatchesRegularExpression('/Transactions du mois<\/div>\s*<div[^>]*>1</', $html);
    }

    // ---------- Solde du mois et solde cumulé ----------

    public function test_le_solde_cumule_additionne_tous_les_mois_alors_que_le_solde_du_mois_ne_compte_que_le_mois_en_cours(): void
    {
        $this->revenu('2026-08-01', 250000);   // mois précédents
        $this->depense('2026-08-10', 100000);
        $this->revenu('2026-09-01', 250000);
        $this->depense('2026-09-10', 80000);
        $this->revenu('2026-10-01', 250000);   // mois en cours
        $this->depense('2026-10-05', 75000);

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        // Solde du mois : 250 000 − 75 000 = 175 000 ; cumulé : 750 000 − 255 000 = 495 000
        $this->assertMatchesRegularExpression('#Solde du mois</div>\s*<div[^>]*>175 000 FCFA<#', $html);
        $this->assertStringContainsString('Solde cumulé : 495 000 FCFA', $html);
    }

    public function test_le_solde_cumule_peut_etre_negatif_et_le_solde_du_mois_positif(): void
    {
        $this->depense('2026-07-10', 500000);   // gros achat passé
        $this->revenu('2026-10-01', 250000);

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertMatchesRegularExpression('#Solde du mois</div>\s*<div[^>]*>250 000 FCFA<#', $html);
        $this->assertStringContainsString('Solde cumulé : -250 000 FCFA', $html);
    }

    public function test_le_solde_cumule_ignore_les_transactions_posterieures_a_aujourd_hui(): void
    {
        $this->revenu('2026-10-01', 100000);
        $this->depense('2026-11-20', 999000); // cas qui ne peut pas être saisi dans l'application, mais qui ne doit pas fausser le solde

        $this->actingAs($this->user)->get('/dashboard')->assertSee('Solde cumulé : 100 000 FCFA');
    }

    public function test_le_solde_cumule_inclut_aujourd_hui_et_exclut_les_donnees_des_autres(): void
    {
        $this->revenu('2026-10-15', 40000);                       // aujourd'hui : inclus
        $this->revenu('2026-10-10', 777000, User::factory()->create()); // autre utilisateur : exclu

        $this->actingAs($this->user)->get('/dashboard')->assertSee('Solde cumulé : 40 000 FCFA')->assertDontSee('777 000');
    }

    public function test_la_carte_du_solde_garde_sa_couleur_et_le_cumule_est_en_petit(): void
    {
        $this->depense('2026-10-05', 5000);

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertMatchesRegularExpression('/Solde du mois.*?sb-negative.*?Solde cumulé : -5 000 FCFA/s', $html);
        $this->assertMatchesRegularExpression('/<div class="small text-muted sb-amount">Solde cumulé/', $html);
    }

    // ---------- Dernières transactions ----------

    public function test_les_5_dernieres_transactions_melangent_revenus_et_depenses(): void
    {
        $this->depense('2026-10-01', 1000, null, null, 'ANCIENNE1');
        $this->depense('2026-10-02', 1000, null, null, 'ANCIENNE2');
        $this->revenu('2026-10-10', 1000, null, 'REVENU-A');
        $this->depense('2026-10-11', 1000, null, null, 'DEPENSE-B');
        $this->revenu('2026-10-12', 1000, null, 'REVENU-C');
        $this->depense('2026-10-13', 1000, null, null, 'DEPENSE-D');
        $this->revenu('2026-10-14', 1000, null, 'REVENU-E');

        $this->actingAs($this->user)->get('/dashboard')
            ->assertSeeInOrder(['REVENU-E', 'DEPENSE-D', 'REVENU-C', 'DEPENSE-B', 'REVENU-A'])
            ->assertDontSee('ANCIENNE1')
            ->assertDontSee('ANCIENNE2');
    }

    public function test_les_dernieres_transactions_ont_un_badge_et_un_signe(): void
    {
        $this->revenu('2026-10-10', 30000);
        $this->depense('2026-10-11', 4000);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertSee('text-bg-success', false)->assertSee('Revenu')
            ->assertSee('text-bg-danger', false)->assertSee('Dépense')
            ->assertSee('+ 30 000 FCFA')
            ->assertSee('− 4 000 FCFA');
    }

    public function test_les_dernieres_transactions_incluent_celles_d_un_mois_precedent(): void
    {
        $this->depense('2026-07-04', 2500, null, null, 'JUILLET');

        $this->actingAs($this->user)->get('/dashboard')->assertSee('JUILLET')->assertSee('04/07/2026');
    }

    // ---------- Données des graphiques ----------

    public function test_repartition_des_depenses_du_mois_par_categorie(): void
    {
        $this->depense('2026-10-02', 3000, $this->alimentation);
        $this->depense('2026-10-03', 2000, $this->alimentation);
        $this->depense('2026-10-04', 5000, $this->transport);
        $this->depense('2026-09-04', 99999, $this->transport); // autre mois : exclue
        $this->revenu('2026-10-05', 250000);                    // un revenu n'entre pas dans le camembert

        $g = $this->graphiques();

        $this->assertCount(2, $g['repartition']);
        $this->assertSame('Alimentation', $g['repartition'][0]['categorie']); // 5000 = 5000 : ordre alphabétique
        $this->assertSame(5000, $g['repartition'][0]['total']);
        $this->assertSame(50, $g['repartition'][0]['pourcentage']);
        $this->assertSame('Transport', $g['repartition'][1]['categorie']);
        $this->assertSame(50, $g['repartition'][1]['pourcentage']);
    }

    public function test_serie_des_6_derniers_mois_remplie_a_zero_quand_il_n_y_a_rien(): void
    {
        $this->revenu('2026-10-05', 250000);
        $this->revenu('2026-08-05', 100000);
        $this->depense('2026-10-06', 75000);
        $this->depense('2026-05-20', 4000);  // 1er des 6 mois
        $this->depense('2026-04-30', 9999);  // trop ancien : exclu

        $serie = $this->graphiques()['serie'];

        $this->assertCount(6, $serie['labels']);
        $this->assertSame(
            [now()->subMonths(5)->translatedFormat('M Y'), now()->translatedFormat('M Y')],
            [$serie['labels'][0], $serie['labels'][5]]
        );
        // mai, juin, juillet, août, septembre, octobre
        $this->assertSame([0, 0, 0, 100000, 0, 250000], $serie['revenus']);
        $this->assertSame([4000, 0, 0, 0, 0, 75000], $serie['depenses']);
    }

    public function test_les_graphiques_n_incluent_pas_les_donnees_des_autres(): void
    {
        $this->depense('2026-10-05', 777000, null, User::factory()->create());

        $g = $this->graphiques();

        $this->assertSame([], $g['repartition']);
        $this->assertSame(array_fill(0, 6, 0), $g['serie']['depenses']);
    }

    public function test_la_courbe_d_evolution_du_solde_est_presente_avec_ses_donnees(): void
    {
        $this->revenu('2026-08-01', 250000);
        $this->depense('2026-09-10', 80000);
        $this->depense('2026-10-05', 5000);

        $g = $this->graphiques();

        $this->assertCount(6, $g['evolution']['labels']);
        $this->assertCount(6, $g['evolution']['valeurs']);
        // mai, juin, juillet : 0 ; août : 250 000 ; septembre : 170 000 ; octobre : 165 000 (= le solde cumulé affiché)
        $this->assertSame([0, 0, 0, 250000, 170000, 165000], $g['evolution']['valeurs']);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertSee('id="graphique-solde"', false)
            ->assertSee('Évolution du solde')
            ->assertSee('Solde cumulé : 165 000 FCFA');
    }

    public function test_la_courbe_est_aussi_presente_sans_aucune_donnee(): void
    {
        $g = $this->graphiques();

        $this->assertSame(array_fill(0, 6, 0), $g['evolution']['valeurs']);
        $this->actingAs($this->user)->get('/dashboard')->assertSee('id="graphique-solde"', false);
    }

    public function test_la_courbe_n_inclut_pas_les_donnees_des_autres_utilisateurs(): void
    {
        $this->revenu('2026-10-02', 777000, User::factory()->create());

        $this->assertSame(array_fill(0, 6, 0), $this->graphiques()['evolution']['valeurs']);
    }

    public function test_la_page_charge_chart_js_en_local_et_les_canevas(): void
    {
        $this->depense('2026-10-05', 1000);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertSee('vendor/chartjs/chart.umd.js', false)
            ->assertSee('js/graphiques.js', false)
            ->assertSee('id="graphique-repartition"', false)
            ->assertSee('id="graphique-mensuel"', false);
    }

    public function test_les_boutons_d_ajout_rapide_sont_presents(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertSee(route('depenses.create'), false)
            ->assertSee(route('revenus.create'), false)
            ->assertSee('Bon retour')->assertSee('Awa');
    }

    public function test_le_menu_met_le_tableau_de_bord_en_surbrillance_et_propose_les_statistiques(): void
    {
        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertMatchesRegularExpression('/class="nav-link active"\s+href="[^"]*\/dashboard"/', $html);
        $this->assertStringContainsString(route('statistiques'), $html);
    }

    public function test_changement_de_mois_les_cartes_suivent_le_calendrier(): void
    {
        $this->depense('2026-10-05', 1000);
        $this->travelTo('2026-11-02 08:00:00');

        $html = $this->actingAs($this->user)->get('/dashboard')->getContent();

        $this->assertMatchesRegularExpression('/Transactions du mois<\/div>\s*<div[^>]*>0</', $html);
        $this->assertSame(1000, $this->graphiques()['serie']['depenses'][4]); // octobre est maintenant l'avant-dernier mois
    }
}
