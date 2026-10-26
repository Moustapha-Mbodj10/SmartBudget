<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Database\Seeders\TransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Module 9 — Données de test (CDC §6.5) : ~6 mois de transactions fictives, clairement marquées. */
class DonneesDeTestTest extends TestCase
{
    use RefreshDatabase;

    private User $awa;

    private User $moussa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-15 10:00:00');
        $this->seed();
        $this->awa = User::where('email', 'awa@smartbudget.sn')->firstOrFail();
        $this->moussa = User::where('email', 'moussa@smartbudget.sn')->firstOrFail();
    }

    public function test_les_deux_utilisateurs_de_demonstration_ont_des_transactions_mais_pas_l_admin(): void
    {
        $this->assertGreaterThan(50, $this->awa->depenses()->count());
        $this->assertGreaterThan(5, $this->awa->revenus()->count());
        $this->assertGreaterThan(20, $this->moussa->depenses()->count());
        $this->assertGreaterThan(5, $this->moussa->revenus()->count());

        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertSame(0, $admin->depenses()->count() + $admin->revenus()->count());
    }

    public function test_toutes_les_donnees_sont_clairement_marquees_comme_test(): void
    {
        $this->assertSame(0, Depense::where('description', 'not like', '[TEST]%')->count());
        $this->assertSame(0, Revenu::where('description', 'not like', '[TEST]%')->count());
        $this->assertSame('[TEST]', TransactionSeeder::MARQUE);
    }

    public function test_les_donnees_couvrent_6_mois_consecutifs_jusqu_au_mois_en_cours(): void
    {
        $mois = DB::table('depenses')->where('user_id', $this->awa->id)
            ->selectRaw("DISTINCT DATE_FORMAT(date, '%Y-%m') as m")->orderBy('m')->pluck('m')->all();

        $this->assertSame(['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], $mois);
    }

    public function test_aucune_date_dans_le_futur_ni_trop_ancienne(): void
    {
        $this->assertSame(0, Depense::where('date', '>', '2026-10-15')->count());
        $this->assertSame(0, Revenu::where('date', '>', '2026-10-15')->count());
        $this->assertSame(0, Depense::where('date', '<', '2026-05-01')->count());
        $this->assertSame(0, Revenu::where('date', '<', '2026-05-01')->count());
    }

    public function test_le_salaire_et_le_loyer_de_l_exemple_du_cahier_des_charges(): void
    {
        $this->assertSame(6, Revenu::where('user_id', $this->awa->id)->where('description', '[TEST] Salaire')->where('montant', 250000)->count());
        $this->assertSame(6, Depense::where('user_id', $this->awa->id)->where('description', '[TEST] Loyer')->where('montant', 75000)->count());
    }

    public function test_tous_les_montants_sont_strictement_positifs(): void
    {
        $this->assertSame(0, Depense::where('montant', '<=', 0)->count());
        $this->assertSame(0, Revenu::where('montant', '<=', 0)->count());
    }

    public function test_coherence_proprietaire_et_type_de_categorie(): void
    {
        // Chaque dépense (revenu) utilise une catégorie de dépense (revenu) appartenant au même utilisateur
        $this->assertSame(0, DB::table('depenses')->join('categories', 'categories.id', '=', 'depenses.categorie_id')
            ->whereColumn('categories.user_id', '!=', 'depenses.user_id')->orWhere('categories.type', '!=', 'depense')->count());
        $this->assertSame(0, DB::table('revenus')->join('categories', 'categories.id', '=', 'revenus.categorie_id')
            ->whereColumn('categories.user_id', '!=', 'revenus.user_id')->orWhere('categories.type', '!=', 'revenu')->count());
    }

    public function test_le_seeder_est_rejouable_sans_doublon(): void
    {
        $avant = [Depense::count(), Revenu::count(), User::count(), Categorie::count()];

        $this->seed();
        $this->seed(TransactionSeeder::class);

        $this->assertSame($avant, [Depense::count(), Revenu::count(), User::count(), Categorie::count()]);
    }

    public function test_rejouer_le_seeder_ne_touche_pas_aux_transactions_reelles(): void
    {
        $reelle = Depense::factory()->create([
            'user_id' => $this->awa->id, 'categorie_id' => $this->awa->categories()->where('type', 'depense')->first()->id,
            'description' => 'Ma vraie dépense', 'montant' => 1234,
        ]);

        $this->seed(TransactionSeeder::class);

        $this->assertModelExists($reelle);
    }

    public function test_le_mois_en_cours_ne_contient_que_les_jours_deja_passes(): void
    {
        // Au 15 octobre : le salaire de Moussa (jour 2) et le loyer d'Awa (jour 5) existent, le courses du 17 et du 24 non
        $this->assertSame(1, Depense::where('user_id', $this->awa->id)->where('date', '2026-10-05')->where('description', '[TEST] Loyer')->count());
        $this->assertSame(0, Depense::where('user_id', $this->awa->id)->where('date', '2026-10-17')->count());
        $this->assertSame(0, Depense::where('user_id', $this->awa->id)->where('date', '2026-10-24')->count());
    }

    public function test_les_donnees_alimentent_le_tableau_de_bord_et_les_graphiques(): void
    {
        $reponse = $this->actingAs($this->awa)->get('/dashboard');

        $reponse->assertOk()
            ->assertSee('250 000 FCFA')
            ->assertDontSee('Vous n\'avez encore saisi aucune transaction.', false);

        preg_match('#<script type="application/json" id="donnees-graphiques">(.*?)</script>#s', $reponse->getContent(), $m);
        $g = json_decode($m[1], true);
        $this->assertNotEmpty($g['repartition']);
        $this->assertCount(6, $g['serie']['labels']);
        foreach ($g['serie']['depenses'] as $total) {
            $this->assertGreaterThan(0, $total, 'chaque mois a des dépenses');
        }
        foreach ($g['serie']['revenus'] as $total) {
            $this->assertGreaterThan(0, $total, 'chaque mois a des revenus');
        }
    }

    public function test_l_historique_montre_les_donnees_de_test_et_les_paginations(): void
    {
        $this->actingAs($this->awa)->get('/historique')->assertOk()->assertSee('[TEST]')->assertSee('page-link', false);
    }

    public function test_les_deux_comptes_ne_voient_que_leurs_propres_donnees(): void
    {
        $this->assertSame(0, Depense::where('user_id', $this->moussa->id)->where('description', '[TEST] Loyer')->where('montant', 75000)->count());

        $this->actingAs($this->moussa)->get('/historique?q=Loyer')->assertSee('60 000 FCFA')->assertDontSee('75 000 FCFA');
    }
}
