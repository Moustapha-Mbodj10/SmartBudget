<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use App\Services\BudgetStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module 7 — Historique unifié (EF7) et recherche multicritère (EF11). */
class HistoriqueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categorie $alimentation;

    private Categorie $transport;

    private Categorie $salaire;

    private Categorie $primes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->alimentation = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation']);
        $this->transport = Categorie::factory()->for($this->user)->create(['nom' => 'Transport']);
        $this->salaire = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Salaire']);
        $this->primes = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Primes']);
    }

    private function depense(string $date, float $montant, string $description = 'Dépense', ?Categorie $cat = null, ?User $user = null): Depense
    {
        $user ??= $this->user;
        $cat ??= $user->is($this->user) ? $this->alimentation : Categorie::factory()->for($user)->create();

        return Depense::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'date' => $date, 'montant' => $montant, 'description' => $description]);
    }

    private function revenu(string $date, float $montant, string $description = 'Revenu', ?Categorie $cat = null, ?User $user = null): Revenu
    {
        $user ??= $this->user;
        $cat ??= $user->is($this->user) ? $this->salaire : Categorie::factory()->for($user)->revenu()->create();

        return Revenu::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'date' => $date, 'montant' => $montant, 'description' => $description]);
    }

    private function page(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->get('/historique'.$query);
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get('/historique')->assertRedirect(route('login'));
    }

    public function test_l_administrateur_n_a_pas_acces_a_l_historique(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/historique')->assertForbidden();
    }

    public function test_un_utilisateur_suspendu_est_deconnecte(): void
    {
        $this->actingAs(User::factory()->suspendu()->create())->get('/historique')->assertRedirect(route('login'));
    }

    // ---------- Liste unifiée (EF7) ----------

    public function test_revenus_et_depenses_sont_dans_une_seule_liste_triee_par_date_decroissante(): void
    {
        $this->depense('2026-10-01', 1000, 'DEP-OCT1');
        $this->revenu('2026-10-05', 2000, 'REV-OCT5');
        $this->depense('2026-10-10', 3000, 'DEP-OCT10');
        $this->revenu('2026-09-20', 4000, 'REV-SEP20');

        $this->page()->assertOk()->assertSeeInOrder(['DEP-OCT10', 'REV-OCT5', 'DEP-OCT1', 'REV-SEP20']);
    }

    public function test_chaque_ligne_a_un_badge_de_type_et_un_signe(): void
    {
        $this->revenu('2026-10-05', 250000);
        $this->depense('2026-10-06', 5000);

        $this->page()
            ->assertSee('text-bg-success', false)->assertSee('Revenu')
            ->assertSee('text-bg-danger', false)->assertSee('Dépense')
            ->assertSee('+ 250 000 FCFA')
            ->assertSee('− 5 000 FCFA')
            ->assertSee('sb-positive', false)
            ->assertSee('sb-negative', false);
    }

    public function test_les_lignes_affichent_date_jj_mm_aaaa_et_categorie(): void
    {
        $this->depense('2026-03-05', 1500.5, 'Marché', $this->transport);

        $this->page()->assertSee('05/03/2026')->assertSee('Transport')->assertSee('Marché')->assertSee('1 500,50 FCFA');
    }

    public function test_l_historique_couvre_tous_les_mois_et_toutes_les_annees(): void
    {
        $this->depense('2019-01-15', 100, 'TRES-ANCIEN');
        $this->depense('2026-10-15', 100, 'RECENT');

        $this->page()->assertSee('TRES-ANCIEN')->assertSee('RECENT');
    }

    public function test_deux_transactions_du_meme_jour_sont_toutes_les_deux_listees(): void
    {
        $this->depense('2026-10-05', 1000, 'MEME-JOUR-DEP');
        $this->revenu('2026-10-05', 2000, 'MEME-JOUR-REV');

        $this->page()->assertSee('MEME-JOUR-DEP')->assertSee('MEME-JOUR-REV');
    }

    public function test_un_depense_et_un_revenu_de_meme_id_ne_se_melangent_pas(): void
    {
        $d = $this->depense('2026-10-05', 1000, 'LA-DEPENSE');
        // On force la collision : un revenu qui porte exactement le même id que la dépense
        $r = Revenu::factory()->create([
            'id' => $d->id, 'user_id' => $this->user->id, 'categorie_id' => $this->salaire->id,
            'date' => '2026-10-05', 'montant' => 2000, 'description' => 'LE-REVENU',
        ]);
        $this->assertSame($d->id, $r->id);

        $html = $this->page()->getContent();

        $this->assertStringContainsString('LA-DEPENSE', $html);
        $this->assertStringContainsString('LE-REVENU', $html);
        $this->assertStringContainsString(route('depenses.edit', $d->id), $html);
        $this->assertStringContainsString(route('revenus.edit', $r->id), $html);
        $this->assertSame(2, substr_count($html, 'aria-label="Modifier"'), 'les deux lignes sont présentes malgré l\'id commun');
    }

    public function test_chaque_ligne_renvoie_vers_la_bonne_page_de_modification(): void
    {
        $d = $this->depense('2026-10-05', 1000);
        $r = $this->revenu('2026-10-06', 2000);
        $r2 = $this->revenu('2026-10-07', 3000);

        $html = $this->page()->getContent();

        $this->assertStringContainsString('/depenses/'.$d->id.'/edit', $html);
        $this->assertStringContainsString('/revenus/'.$r->id.'/edit', $html);
        $this->assertStringContainsString('/revenus/'.$r2->id.'/edit', $html);
    }

    public function test_les_donnees_des_autres_utilisateurs_sont_absentes(): void
    {
        $autre = User::factory()->create();
        $this->depense('2026-10-05', 777000, 'DEP-AUTRE', null, $autre);
        $this->revenu('2026-10-05', 888000, 'REV-AUTRE', null, $autre);
        $this->depense('2026-10-06', 1000, 'MA-DEPENSE');

        $html = $this->page()->getContent();

        $this->assertStringContainsString('MA-DEPENSE', $html);
        $this->assertStringNotContainsString('DEP-AUTRE', $html);
        $this->assertStringNotContainsString('REV-AUTRE', $html);
        $this->assertStringNotContainsString('777 000', $html);
        $this->assertStringNotContainsString('888 000', $html);
    }

    // ---------- Pagination ----------

    public function test_la_liste_est_paginee_par_10_avec_les_deux_types_melanges(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->depense(sprintf('2026-05-%02d', $i), 100, sprintf('DEP%02d', $i));
        }
        for ($i = 1; $i <= 11; $i++) {
            $this->revenu(sprintf('2026-06-%02d', $i), 100, sprintf('REV%02d', $i));
        }

        $page1 = $this->page();
        $this->assertSame(10, substr_count($page1->getContent(), 'aria-label="Modifier"'));
        $page1->assertSee('REV11')->assertSee('REV02')->assertDontSee('REV01')->assertSee('25 transactions');

        $page3 = $this->page('?page=3');
        $this->assertSame(5, substr_count($page3->getContent(), 'aria-label="Modifier"'));
        $page3->assertSee('DEP05')->assertSee('DEP01')->assertDontSee('DEP06');
    }

    public function test_aucune_transaction_n_est_perdue_ni_dupliquee_entre_les_pages(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->depense('2026-05-10', 100, sprintf('D%02d', $i)); // même date : l'ordre doit rester stable
            $this->revenu('2026-05-10', 100, sprintf('R%02d', $i));
        }

        $vues = [];
        foreach ([1, 2, 3] as $numero) {
            preg_match_all('/\b([DR]\d{2})\b/', $this->page('?page='.$numero)->getContent(), $m);
            $vues = array_merge($vues, array_unique($m[1]));
        }

        $this->assertCount(24, $vues);
        $this->assertCount(24, array_unique($vues), 'aucun doublon entre les pages');
    }

    public function test_la_pagination_est_entierement_en_francais(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->depense('2026-05-10', 100, "D$i");
        }

        // Sur ordinateur, la pagination affiche « Affichage de 1 à 10 sur 12 résultats » (et non « Showing 1 to 10 of 12 results »)
        $this->page()
            ->assertSee('Affichage de')
            ->assertSee('résultats')
            ->assertDontSee('Showing')
            ->assertDontSee('results')
            ->assertDontSee('Go to page');
    }

    public function test_les_filtres_sont_conserves_dans_les_liens_de_pagination(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->depense('2026-05-10', 100, "Taxi $i");
        }

        $this->page('?q=Taxi&type=depense')->assertSee('page=2', false)->assertSee('q=Taxi', false);
    }

    // ---------- Totaux ----------

    public function test_totaux_revenus_depenses_et_solde(): void
    {
        $this->revenu('2026-10-01', 250000);
        $this->depense('2026-10-02', 75000);
        $this->depense('2026-10-03', 5000);

        $this->page()->assertSee('250 000 FCFA')->assertSee('80 000 FCFA')->assertSee('170 000 FCFA');
    }

    public function test_les_totaux_couvrent_toutes_les_pages_pas_seulement_la_page_affichee(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->depense('2026-05-10', 1000, "D$i");
        }

        $this->page()->assertSee('15 000 FCFA');
    }

    public function test_totaux_et_solde_negatif(): void
    {
        $this->depense('2026-10-02', 5000);

        $this->page()->assertSee('-5 000 FCFA')->assertSee('sb-negative', false);
    }

    // ---------- Recherche multicritère (EF11) ----------

    public function test_filtre_mot_cle(): void
    {
        $this->depense('2026-10-01', 1000, 'Loyer octobre');
        $this->revenu('2026-10-02', 2000, 'Loyer perçu');
        $this->depense('2026-10-03', 3000, 'Courses');

        $this->page('?q=loyer')->assertSee('Loyer octobre')->assertSee('Loyer perçu')->assertDontSee('Courses');
    }

    public function test_le_mot_cle_traite_pourcent_et_underscore_comme_du_texte(): void
    {
        $this->depense('2026-10-01', 1000, 'Remise 50% été');
        $this->depense('2026-10-02', 1000, 'Autre chose');
        $this->depense('2026-10-03', 1000, 'a_b');
        $this->depense('2026-10-04', 1000, 'axb');

        $this->page('?q='.urlencode('%'))->assertSee('Remise 50% été')->assertDontSee('Autre chose');
        $this->page('?q='.urlencode('a_b'))->assertSee('a_b')->assertDontSee('axb');
    }

    public function test_filtre_par_type(): void
    {
        $this->depense('2026-10-01', 1000, 'UNE-DEPENSE');
        $this->revenu('2026-10-02', 2000, 'UN-REVENU');

        $this->page('?type=depense')->assertSee('UNE-DEPENSE')->assertDontSee('UN-REVENU');
        $this->page('?type=revenu')->assertSee('UN-REVENU')->assertDontSee('UNE-DEPENSE');
        $this->page('?type=')->assertSee('UNE-DEPENSE')->assertSee('UN-REVENU');
    }

    public function test_les_totaux_suivent_le_filtre_de_type(): void
    {
        $this->revenu('2026-10-01', 250000);
        $this->depense('2026-10-02', 75000);

        $stats = new BudgetStats($this->user);
        $this->assertSame(['revenus' => 0.0, 'depenses' => 75000.0], $stats->totauxFiltres(['type' => 'depense']));
        $this->assertSame(['revenus' => 250000.0, 'depenses' => 0.0], $stats->totauxFiltres(['type' => 'revenu']));
    }

    public function test_filtre_par_categorie_de_depense_et_de_revenu(): void
    {
        $this->depense('2026-10-01', 1000, 'PAIN', $this->alimentation);
        $this->depense('2026-10-02', 1000, 'TAXI', $this->transport);
        $this->revenu('2026-10-03', 2000, 'PAIE', $this->salaire);
        $this->revenu('2026-10-04', 2000, 'BONUS', $this->primes);

        $this->page('?categorie_id='.$this->transport->id)->assertSee('TAXI')->assertDontSee('PAIN')->assertDontSee('PAIE')->assertDontSee('BONUS');
        $this->page('?categorie_id='.$this->primes->id)->assertSee('BONUS')->assertDontSee('PAIE')->assertDontSee('TAXI');
    }

    public function test_une_categorie_ne_peut_pas_faire_fuiter_les_donnees_d_un_autre(): void
    {
        $autre = User::factory()->create();
        $catAutre = Categorie::factory()->for($autre)->create(['nom' => 'CatAutre']);
        $this->depense('2026-10-01', 777000, 'SECRET', $catAutre, $autre);

        $this->page('?categorie_id='.$catAutre->id)->assertSee('Aucune transaction trouvée.')->assertDontSee('SECRET');
    }

    public function test_filtre_par_dates_bornes_incluses(): void
    {
        $this->depense('2026-04-30', 100, 'AVANT');
        $this->depense('2026-05-01', 100, 'DEBUT');
        $this->revenu('2026-05-31', 100, 'FIN');
        $this->revenu('2026-06-01', 100, 'APRES');

        $this->page('?date_debut=2026-05-01&date_fin=2026-05-31')
            ->assertSee('DEBUT')->assertSee('FIN')->assertDontSee('AVANT')->assertDontSee('APRES');
    }

    public function test_filtre_date_debut_seule_et_date_fin_seule(): void
    {
        $this->depense('2026-01-01', 100, 'JANVIER');
        $this->depense('2026-12-01', 100, 'DECEMBRE');

        $this->page('?date_debut=2026-06-01')->assertSee('DECEMBRE')->assertDontSee('JANVIER');
        $this->page('?date_fin=2026-06-01')->assertSee('JANVIER')->assertDontSee('DECEMBRE');
    }

    public function test_filtre_par_montants_bornes_incluses(): void
    {
        $this->depense('2026-10-01', 999, 'PETIT');
        $this->depense('2026-10-02', 1000, 'BORNEMIN');
        $this->revenu('2026-10-03', 5000, 'BORNEMAX');
        $this->revenu('2026-10-04', 5001, 'GROS');

        $this->page('?montant_min=1000&montant_max=5000')
            ->assertSee('BORNEMIN')->assertSee('BORNEMAX')->assertDontSee('PETIT')->assertDontSee('GROS');
    }

    public function test_tous_les_filtres_se_combinent(): void
    {
        $this->depense('2026-05-10', 8000, 'Taxi aéroport', $this->transport);     // doit ressortir
        $this->depense('2026-05-10', 1000, 'Taxi ville', $this->transport);        // montant trop bas
        $this->depense('2025-01-10', 8000, 'Taxi ancien', $this->transport);       // date trop ancienne
        $this->depense('2026-05-10', 8000, 'Taxi autre catégorie', $this->alimentation);
        $this->revenu('2026-05-10', 8000, 'Taxi remboursé', $this->salaire);       // mauvais type
        $this->depense('2026-05-10', 8000, 'Restaurant', $this->transport);        // mauvais mot-clé

        $this->page('?'.http_build_query([
            'q' => 'taxi', 'type' => 'depense', 'categorie_id' => $this->transport->id,
            'date_debut' => '2026-01-01', 'date_fin' => '2026-12-31', 'montant_min' => 5000, 'montant_max' => 10000,
        ]))
            ->assertSee('Taxi aéroport')
            ->assertDontSee('Taxi ville')->assertDontSee('Taxi ancien')->assertDontSee('Taxi autre catégorie')
            ->assertDontSee('Taxi remboursé')->assertDontSee('Restaurant')
            ->assertSee('1 transaction')
            ->assertSee('Dépenses (résultats)');
    }

    public function test_les_valeurs_des_filtres_restent_dans_le_formulaire(): void
    {
        $this->page('?q=loyer&type=revenu&montant_min=500&date_debut=2026-01-01')
            ->assertSee('value="loyer"', false)
            ->assertSee('<option value="revenu" selected>', false)
            ->assertSee('value="500"', false)
            ->assertSee('value="2026-01-01"', false)
            ->assertSee('Réinitialiser');
    }

    public function test_le_selecteur_de_categorie_groupe_depenses_et_revenus(): void
    {
        $this->page()
            ->assertSee('<optgroup label="Dépenses">', false)
            ->assertSee('<optgroup label="Revenus">', false)
            ->assertSee('Alimentation')->assertSee('Salaire');
    }

    // ---------- T9 : recherche sans résultat ----------

    /** T9 */
    public function test_t9_recherche_sans_resultat_affiche_aucune_transaction_trouvee(): void
    {
        $this->depense('2026-10-01', 1000, 'Courses');

        $this->page('?q=introuvable')->assertOk()->assertSee('Aucune transaction trouvée.');
    }

    public function test_t9_une_combinaison_sans_resultat_n_affiche_ni_totaux_ni_pagination(): void
    {
        $this->depense('2026-10-01', 1000, 'Courses');

        $reponse = $this->page('?q=Courses&type=revenu');

        $reponse->assertSee('Aucune transaction trouvée.')->assertDontSee('(résultats)')->assertDontSee('page-link', false);
    }

    public function test_historique_vide_invite_a_saisir_une_premiere_transaction(): void
    {
        $this->page()
            ->assertOk()
            ->assertSee('Aucune transaction enregistrée.')
            ->assertSee(route('depenses.create'), false)
            ->assertSee(route('revenus.create'), false)
            ->assertDontSee('Aucune transaction trouvée.');
    }

    // ---------- Filtres invalides ----------

    public function test_un_filtre_invalide_est_ignore_avec_un_message(): void
    {
        $this->depense('2026-10-01', 1000, 'VISIBLE');

        $this->page('?date_debut=pas-une-date&montant_min=abc&type=autre')
            ->assertOk()
            ->assertSee('VISIBLE')
            ->assertSee('date de début')
            ->assertSee('montant minimum');
    }

    public function test_intervalles_inverses_affichent_un_message_sans_filtrer(): void
    {
        $this->depense('2026-05-10', 1000, 'VISIBLE');

        $this->page('?date_debut=2026-06-01&date_fin=2026-05-01&montant_min=9000&montant_max=100')
            ->assertOk()
            ->assertSee('VISIBLE')
            ->assertSee('La date de fin doit être postérieure à la date de début.')
            ->assertSee('Le montant maximum doit être supérieur au montant minimum.');
    }

    public function test_une_injection_dans_les_filtres_est_inoffensive(): void
    {
        $this->depense('2026-10-01', 1000, 'VISIBLE');

        $this->page('?q='.urlencode("'; DROP TABLE depenses; --").'&categorie_id='.urlencode('1 OR 1=1').'&type='.urlencode("depense' OR '1'='1"))
            ->assertOk();
        $this->assertSame(1, Depense::count());
    }

    public function test_les_filtres_donnes_en_tableau_ne_provoquent_pas_d_erreur(): void
    {
        $this->depense('2026-10-01', 1000, 'VISIBLE');

        $this->page('?q[]=a&type[x]=y&categorie_id[]=1')->assertOk()->assertSee('VISIBLE');
    }

    // ---------- Suppression depuis l'historique ----------

    public function test_chaque_ligne_propose_une_suppression_avec_confirmation_adaptee_au_type(): void
    {
        $d = $this->depense('2026-10-05', 1000, 'UNE-DEPENSE');
        $r = Revenu::factory()->create([
            'user_id' => $this->user->id, 'categorie_id' => $this->salaire->id, 'date' => '2026-10-06', 'montant' => 2000, 'description' => 'UN-REVENU',
        ]);

        $html = $this->page('?q=UN')->getContent();

        $this->assertStringContainsString('action="'.route('depenses.destroy', $d->id).'"', $html);
        $this->assertStringContainsString('action="'.route('revenus.destroy', $r->id).'"', $html);
        $this->assertStringContainsString('data-confirm="Supprimer cette dépense de 1 000 FCFA ?"', $html);
        $this->assertStringContainsString('data-confirm="Supprimer ce revenu de 2 000 FCFA ?"', $html);
        $this->assertStringContainsString('name="retour" value="historique"', $html);
        $this->assertSame(2, substr_count($html, 'aria-label="Supprimer"'));
    }

    public function test_supprimer_une_depense_depuis_l_historique_y_ramene_avec_un_message(): void
    {
        $d = $this->depense('2026-10-05', 1000, 'A-SUPPRIMER');

        $this->actingAs($this->user)->delete("/depenses/{$d->id}", ['retour' => 'historique'])
            ->assertRedirect(route('historique'))
            ->assertSessionHas('success', 'Dépense supprimée avec succès.');

        $this->assertModelMissing($d);
    }

    public function test_supprimer_un_revenu_depuis_l_historique_y_ramene_avec_un_message(): void
    {
        $r = $this->revenu('2026-10-05', 2000, 'A-SUPPRIMER');

        $this->actingAs($this->user)->delete("/revenus/{$r->id}", ['retour' => 'historique'])
            ->assertRedirect(route('historique'))
            ->assertSessionHas('success', 'Revenu supprimé avec succès.');

        $this->assertModelMissing($r);
    }

    public function test_la_suppression_depuis_l_historique_conserve_les_filtres_et_la_page_connus(): void
    {
        $d = $this->depense('2026-10-05', 1000);

        $this->actingAs($this->user)->delete("/depenses/{$d->id}", [
            'retour' => 'historique',
            'retour_query' => 'q=Taxi&type=depense&date_debut=2026-01-01&montant_min=500&page=2&evil=1&redirect=http://evil.example',
        ])->assertRedirect(route('historique', ['q' => 'Taxi', 'type' => 'depense', 'date_debut' => '2026-01-01', 'montant_min' => '500', 'page' => '2']));
    }

    public function test_une_adresse_de_retour_hostile_est_ignoree(): void
    {
        $d = $this->depense('2026-10-05', 1000);
        $r = $this->depense('2026-10-06', 1000);
        $autre = $this->depense('2026-10-07', 1000);

        $this->actingAs($this->user)->delete("/depenses/{$d->id}", ['retour' => 'http://evil.example/'])
            ->assertRedirect(route('depenses.index'));
        $this->actingAs($this->user)->delete("/depenses/{$r->id}", ['retour' => '//evil.example'])
            ->assertRedirect(route('depenses.index'));
        $this->actingAs($this->user)->delete("/depenses/{$autre->id}", ['retour' => 'historique', 'retour_query' => "q=x\r\nLocation: http://evil.example"])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, Depense::count());
    }

    public function test_supprimer_depuis_l_historique_la_transaction_d_un_autre_donne_403(): void
    {
        $autre = User::factory()->create();
        $d = $this->depense('2026-10-05', 1000, 'PRIVEE', null, $autre);
        $r = $this->revenu('2026-10-05', 1000, 'PRIVE', null, $autre);

        $this->actingAs($this->user)->delete("/depenses/{$d->id}", ['retour' => 'historique'])->assertForbidden();
        $this->actingAs($this->user)->delete("/revenus/{$r->id}", ['retour' => 'historique'])->assertForbidden();

        $this->assertModelExists($d);
        $this->assertModelExists($r);
    }

    public function test_l_administrateur_ne_peut_pas_supprimer_depuis_l_historique(): void
    {
        $d = $this->depense('2026-10-05', 1000);

        $this->actingAs(User::factory()->admin()->create())->delete("/depenses/{$d->id}", ['retour' => 'historique'])->assertForbidden();
        $this->assertModelExists($d);
    }

    public function test_supprimer_la_derniere_ligne_de_la_derniere_page_ramene_a_la_page_precedente(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->depense(sprintf('2026-05-%02d', $i), 100, sprintf('LIGNE%02d', $i));
        }
        $derniere = Depense::where('description', 'LIGNE01')->firstOrFail(); // la plus ancienne = seule ligne de la page 2

        $this->actingAs($this->user)->get('/historique?page=2')->assertSee('LIGNE01');

        $reponse = $this->actingAs($this->user)->followingRedirects()
            ->delete("/depenses/{$derniere->id}", ['retour' => 'historique', 'retour_query' => 'page=2']);

        $reponse->assertOk()->assertSee('Dépense supprimée avec succès.')->assertSee('LIGNE11')->assertDontSee('LIGNE01');
        $this->assertSame(10, substr_count($reponse->getContent(), 'aria-label="Modifier"'), 'la page 1 est affichée, pas une page vide');
    }

    public function test_une_page_hors_limites_redirige_vers_la_derniere_page_existante(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->depense('2026-05-10', 100, "D$i");
        }

        $this->page('?page=99&type=depense')->assertRedirect(route('historique', ['page' => 2, 'type' => 'depense']));
    }

    public function test_une_page_hors_limites_sans_aucune_transaction_ne_boucle_pas(): void
    {
        $this->page('?page=5')->assertRedirect(route('historique', ['page' => 1]));
        $this->page('?page=1')->assertOk()->assertSee('Aucune transaction enregistrée.');
    }

    // ---------- Navigation ----------

    public function test_le_menu_met_l_historique_en_surbrillance(): void
    {
        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/class="nav-link active"\s+href="[^"]*\/historique"/', $html);
    }

    public function test_le_tableau_de_bord_propose_tout_voir_vers_l_historique(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertSee(route('historique'), false)->assertSee('Tout voir');
    }
}
