<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module 4 — Gestion des revenus (EF4) : mêmes règles que les dépenses, catégories de type « revenu ». */
class RevenuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categorie $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cat = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Salaire']);
    }

    private function revenu(array $attrs = [], ?User $user = null, ?Categorie $cat = null): Revenu
    {
        $user ??= $this->user;
        $cat ??= $user->is($this->user) ? $this->cat : Categorie::factory()->for($user)->revenu()->create();

        return Revenu::factory()->create(array_merge(
            ['user_id' => $user->id, 'categorie_id' => $cat->id, 'montant' => 250000, 'date' => '2026-10-01', 'description' => 'Paie'],
            $attrs
        ));
    }

    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'montant' => '250000',
            'date' => now()->format('Y-m-d'),
            'categorie_id' => $this->cat->id,
            'description' => 'Salaire octobre',
        ], $surcharge);
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $r = $this->revenu();

        $this->get('/revenus')->assertRedirect(route('login'));
        $this->get('/revenus/create')->assertRedirect(route('login'));
        $this->post('/revenus', $this->donnees())->assertRedirect(route('login'));
        $this->get("/revenus/{$r->id}/edit")->assertRedirect(route('login'));
        $this->put("/revenus/{$r->id}", $this->donnees())->assertRedirect(route('login'));
        $this->delete("/revenus/{$r->id}")->assertRedirect(route('login'));
    }

    public function test_l_administrateur_n_a_acces_a_aucune_route_de_revenus(): void
    {
        $admin = User::factory()->admin()->create();
        $r = $this->revenu();

        $this->actingAs($admin)->get('/revenus')->assertForbidden();
        $this->actingAs($admin)->get('/revenus/create')->assertForbidden();
        $this->actingAs($admin)->post('/revenus', $this->donnees())->assertForbidden();
        $this->actingAs($admin)->get("/revenus/{$r->id}/edit")->assertForbidden();
        $this->actingAs($admin)->delete("/revenus/{$r->id}")->assertForbidden();
        $this->assertModelExists($r);
    }

    // ---------- Liste ----------

    public function test_la_liste_affiche_montant_formate_date_et_categorie(): void
    {
        $this->revenu(['montant' => 250000, 'date' => '2026-03-05']);

        $this->actingAs($this->user)->get('/revenus')
            ->assertOk()
            ->assertSee('Revenus')
            ->assertSee('250 000 FCFA')
            ->assertSee('05/03/2026')
            ->assertSee('Salaire')
            ->assertSee('Paie');
    }

    public function test_la_liste_est_triee_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        $this->revenu(['description' => 'ANCIEN', 'date' => '2026-01-01']);
        $this->revenu(['description' => 'RECENT', 'date' => '2026-09-01']);

        $this->actingAs($this->user)->get('/revenus')->assertSeeInOrder(['RECENT', 'ANCIEN']);
    }

    public function test_la_liste_n_affiche_que_les_revenus_de_l_utilisateur(): void
    {
        $this->revenu(['description' => 'MonRevenu']);
        $this->revenu(['description' => 'RevenuDeLAutre'], User::factory()->create());

        $this->actingAs($this->user)->get('/revenus')
            ->assertSee('MonRevenu')
            ->assertDontSee('RevenuDeLAutre');
    }

    public function test_la_liste_est_paginee_par_10(): void
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->revenu(['description' => sprintf('REV%02d', $i), 'date' => '2026-06-'.sprintf('%02d', $i)]);
        }

        $page1 = $this->actingAs($this->user)->get('/revenus');
        $page1->assertSee('REV23')->assertSee('REV14')->assertDontSee('REV13');
        $this->assertSame(10, substr_count($page1->getContent(), 'data-confirm="Supprimer ce revenu'));
        $this->actingAs($this->user)->get('/revenus?page=3')->assertSee('REV03')->assertDontSee('REV04');
    }

    public function test_la_liste_vide_utilise_le_bon_genre_grammatical(): void
    {
        $this->actingAs($this->user)->get('/revenus')
            ->assertSee('Aucun revenu enregistré. Commencez par saisir votre première transaction.')
            ->assertDontSee('Aucune revenu');
    }

    public function test_la_liste_affiche_le_total(): void
    {
        $this->revenu(['montant' => 100000]);
        $this->revenu(['montant' => 50000]);

        $this->actingAs($this->user)->get('/revenus')->assertSee('Total : 150 000 FCFA');
    }

    public function test_les_montants_de_revenus_sont_en_vert(): void
    {
        $this->revenu();

        $this->actingAs($this->user)->get('/revenus')->assertSee('text-success', false);
    }

    // ---------- Séparation avec les dépenses ----------

    public function test_les_depenses_n_apparaissent_pas_dans_les_revenus_et_inversement(): void
    {
        $this->revenu(['description' => 'JeSuisUnRevenu']);
        $catDep = Categorie::factory()->for($this->user)->create(['type' => 'depense']);
        Depense::factory()->create(['user_id' => $this->user->id, 'categorie_id' => $catDep->id, 'description' => 'JeSuisUneDepense']);

        $this->actingAs($this->user)->get('/revenus')
            ->assertSee('JeSuisUnRevenu')->assertDontSee('JeSuisUneDepense');
        $this->actingAs($this->user)->get('/depenses')
            ->assertSee('JeSuisUneDepense')->assertDontSee('JeSuisUnRevenu');
    }

    public function test_les_identifiants_de_depenses_et_de_revenus_ne_se_melangent_pas(): void
    {
        $depense = Depense::factory()->create(['user_id' => $this->user->id]);

        // Un revenu portant un autre id : l'id d'une dépense n'ouvre jamais un revenu
        $this->assertSame(404, $this->actingAs($this->user)->get('/revenus/'.($depense->id + 500).'/edit')->status());
        $this->actingAs($this->user)->delete('/revenus/'.($depense->id + 500))->assertNotFound();
        $this->assertModelExists($depense);
    }

    // ---------- Formulaire ----------

    public function test_le_formulaire_propose_uniquement_les_categories_de_revenu_de_l_utilisateur(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation', 'type' => 'depense']);
        Categorie::factory()->for(User::factory()->create())->revenu()->create(['nom' => 'CategorieDeLAutre']);

        $this->actingAs($this->user)->get('/revenus/create')
            ->assertOk()
            ->assertSee('Ajouter un revenu')
            ->assertSee('Salaire')
            ->assertDontSee('Alimentation')
            ->assertDontSee('CategorieDeLAutre');
    }

    public function test_le_formulaire_dit_nouveau_revenu(): void
    {
        $this->actingAs($this->user)->get('/revenus/create')
            ->assertSee('Nouveau revenu')
            ->assertDontSee('Nouvelle revenu');
    }

    public function test_le_formulaire_sans_categorie_de_revenu_invite_a_en_creer_une(): void
    {
        $this->cat->delete();

        $this->actingAs($this->user)->get('/revenus/create')
            ->assertSee('Vous n\'avez encore aucune catégorie de revenu.', false)
            ->assertSee(route('categories.index'), false);
    }

    public function test_le_formulaire_de_modification_est_prerempli(): void
    {
        $r = $this->revenu(['montant' => 75000, 'date' => '2026-02-14', 'description' => 'Prime']);

        $this->actingAs($this->user)->get("/revenus/{$r->id}/edit")
            ->assertOk()
            ->assertSee('Modifier un revenu')
            ->assertSee('value="75000.00"', false)
            ->assertSee('value="2026-02-14"', false)
            ->assertSee('value="Prime"', false);
    }

    // ---------- Ajout ----------

    public function test_ajout_d_un_revenu(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees())
            ->assertRedirect(route('revenus.index'))
            ->assertSessionHas('success', 'Revenu ajouté avec succès.');

        $this->assertDatabaseHas('revenus', [
            'user_id' => $this->user->id, 'categorie_id' => $this->cat->id, 'montant' => '250000.00', 'description' => 'Salaire octobre',
        ]);
        $this->assertSame(0, Depense::count(), 'Un revenu ne doit pas créer de dépense');
        $this->actingAs($this->user)->get('/revenus')->assertSee('250 000 FCFA');
    }

    public function test_ajout_avec_centimes_et_sans_description(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['montant' => '1500.50', 'description' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('revenus', ['montant' => '1500.50', 'description' => null]);
    }

    public function test_ajout_montant_negatif_vide_ou_nul_refuse(): void
    {
        foreach (['-500', '', '0', 'abc', '10.001'] as $montant) {
            $this->actingAs($this->user)->post('/revenus', $this->donnees(['montant' => $montant]))
                ->assertSessionHasErrors('montant');
        }
        $this->assertSame(0, Revenu::count());
    }

    public function test_message_montant_strictement_positif(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['montant' => '-1']))
            ->assertSessionHasErrors(['montant' => 'Le montant doit être strictement positif.']);
    }

    public function test_ajout_date_future_ou_absente_refusee(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('date');
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['date' => '']))->assertSessionHasErrors('date');
        $this->assertSame(0, Revenu::count());
    }

    public function test_ajout_date_d_aujourd_hui_acceptee(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees())->assertSessionHasNoErrors();
    }

    public function test_ajout_avec_une_categorie_de_depense_est_refuse(): void
    {
        $catDepense = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation', 'type' => 'depense']);

        $this->actingAs($this->user)->post('/revenus', $this->donnees(['categorie_id' => $catDepense->id]))
            ->assertSessionHasErrors('categorie_id');
        $this->assertSame(0, Revenu::count());
    }

    public function test_ajout_avec_la_categorie_d_un_autre_utilisateur_est_refuse(): void
    {
        $autre = Categorie::factory()->for(User::factory()->create())->revenu()->create();

        $this->actingAs($this->user)->post('/revenus', $this->donnees(['categorie_id' => $autre->id]))
            ->assertSessionHasErrors('categorie_id');
    }

    public function test_ajout_categorie_obligatoire_et_inexistante(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['categorie_id' => '']))
            ->assertSessionHasErrors(['categorie_id' => 'Veuillez choisir une catégorie.']);
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['categorie_id' => 99999]))
            ->assertSessionHasErrors('categorie_id');
    }

    public function test_ajout_description_trop_longue_refusee(): void
    {
        $this->actingAs($this->user)->post('/revenus', $this->donnees(['description' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('description');
    }

    public function test_ajout_ignore_un_user_id_envoye_dans_le_formulaire(): void
    {
        $autre = User::factory()->create();

        $this->actingAs($this->user)->post('/revenus', $this->donnees(['user_id' => $autre->id]));

        $this->assertSame($this->user->id, Revenu::firstOrFail()->user_id);
    }

    // ---------- Modification ----------

    public function test_modification_d_un_revenu(): void
    {
        $r = $this->revenu();
        $autreCat = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Activité commerciale']);

        $this->actingAs($this->user)->put("/revenus/{$r->id}", $this->donnees([
            'montant' => '300000', 'date' => '2026-08-20', 'categorie_id' => $autreCat->id, 'description' => 'Ventes',
        ]))
            ->assertRedirect(route('revenus.index'))
            ->assertSessionHas('success', 'Revenu modifié avec succès.');

        $this->assertDatabaseHas('revenus', [
            'id' => $r->id, 'montant' => '300000.00', 'categorie_id' => $autreCat->id, 'description' => 'Ventes',
        ]);
    }

    public function test_modification_invalide_ne_change_rien(): void
    {
        $r = $this->revenu(['montant' => 1000]);

        $this->actingAs($this->user)->put("/revenus/{$r->id}", $this->donnees(['montant' => '-1']))
            ->assertSessionHasErrors('montant');

        $this->assertSame('1000.00', $r->fresh()->montant);
    }

    public function test_modification_vers_une_categorie_de_depense_est_refusee(): void
    {
        $r = $this->revenu();
        $catDepense = Categorie::factory()->for($this->user)->create(['type' => 'depense']);

        $this->actingAs($this->user)->put("/revenus/{$r->id}", $this->donnees(['categorie_id' => $catDepense->id]))
            ->assertSessionHasErrors('categorie_id');
        $this->assertSame($this->cat->id, $r->fresh()->categorie_id);
    }

    public function test_la_modification_ne_change_pas_le_proprietaire(): void
    {
        $r = $this->revenu();

        $this->actingAs($this->user)->put("/revenus/{$r->id}", $this->donnees(['user_id' => User::factory()->create()->id]));

        $this->assertSame($this->user->id, $r->fresh()->user_id);
    }

    public function test_un_revenu_inexistant_donne_404(): void
    {
        $this->actingAs($this->user)->get('/revenus/9999/edit')->assertNotFound();
        $this->actingAs($this->user)->put('/revenus/9999', $this->donnees())->assertNotFound();
        $this->actingAs($this->user)->delete('/revenus/9999')->assertNotFound();
    }

    // ---------- Isolation entre utilisateurs (T7 côté revenus) ----------

    public function test_t7_acceder_au_revenu_d_un_autre_utilisateur_donne_403(): void
    {
        $r = $this->revenu(['description' => 'Prive', 'montant' => 1000], User::factory()->create());

        $this->actingAs($this->user)->get("/revenus/{$r->id}/edit")->assertForbidden();
        $this->actingAs($this->user)->put("/revenus/{$r->id}", $this->donnees(['montant' => '1']))->assertForbidden();
        $this->actingAs($this->user)->delete("/revenus/{$r->id}")->assertForbidden();

        $r->refresh();
        $this->assertSame('1000.00', $r->montant);
        $this->assertSame('Prive', $r->description);
    }

    public function test_t7_meme_avec_des_donnees_invalides_la_modification_d_un_autre_donne_403(): void
    {
        $r = $this->revenu([], User::factory()->create());

        $this->actingAs($this->user)->put("/revenus/{$r->id}", ['montant' => ''])->assertForbidden();
    }

    // ---------- Suppression ----------

    public function test_suppression_d_un_revenu(): void
    {
        $r = $this->revenu();

        $this->actingAs($this->user)->delete("/revenus/{$r->id}")
            ->assertRedirect(route('revenus.index'))
            ->assertSessionHas('success', 'Revenu supprimé avec succès.');

        $this->assertModelMissing($r);
    }

    public function test_la_suppression_demande_une_confirmation_au_masculin(): void
    {
        $this->revenu(['montant' => 5000]);

        $this->actingAs($this->user)->get('/revenus')
            ->assertSee('data-confirm="Supprimer ce revenu de 5 000 FCFA ?"', false);
    }

    public function test_supprimer_un_revenu_libere_sa_categorie(): void
    {
        $r = $this->revenu();
        $this->assertTrue($this->cat->estUtilisee());

        $this->actingAs($this->user)->delete("/revenus/{$r->id}");

        $this->assertFalse($this->cat->fresh()->estUtilisee());
    }

    // ---------- Recherche et filtres ----------

    public function test_filtres_combines_sur_les_revenus(): void
    {
        $prime = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Primes']);
        $this->revenu(['description' => 'Prime annuelle', 'montant' => 80000, 'date' => '2026-05-10'], $this->user, $prime);
        $this->revenu(['description' => 'Prime petite', 'montant' => 1000, 'date' => '2026-05-10'], $this->user, $prime);
        $this->revenu(['description' => 'Prime ancienne', 'montant' => 80000, 'date' => '2025-01-10'], $this->user, $prime);
        $this->revenu(['description' => 'Prime autre catégorie', 'montant' => 80000, 'date' => '2026-05-10']);

        $this->actingAs($this->user)->get('/revenus?'.http_build_query([
            'q' => 'prime', 'categorie_id' => $prime->id, 'date_debut' => '2026-01-01', 'montant_min' => 5000,
        ]))
            ->assertSee('Prime annuelle')
            ->assertDontSee('Prime petite')
            ->assertDontSee('Prime ancienne')
            ->assertDontSee('Prime autre catégorie')
            ->assertSee('Total des résultats : 80 000 FCFA');
    }

    public function test_recherche_sans_resultat_affiche_aucune_transaction_trouvee(): void
    {
        $this->revenu();

        $this->actingAs($this->user)->get('/revenus?q=introuvable')->assertSee('Aucune transaction trouvée.');
    }

    public function test_la_recherche_ne_voit_pas_les_revenus_des_autres(): void
    {
        $this->revenu(['description' => 'SecretAutre'], User::factory()->create());

        $this->actingAs($this->user)->get('/revenus?q=SecretAutre')->assertSee('Aucune transaction trouvée.');
    }

    public function test_le_filtre_de_categorie_ne_propose_que_des_categories_de_revenu(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation', 'type' => 'depense']);

        $this->actingAs($this->user)->get('/revenus')->assertSee('Salaire')->assertDontSee('Alimentation');
    }

    // ---------- Navigation ----------

    public function test_le_menu_met_revenus_en_surbrillance(): void
    {
        $html = $this->actingAs($this->user)->get('/revenus')->getContent();

        $this->assertMatchesRegularExpression('/class="nav-link active"\s+href="[^"]*\/revenus"/', $html);
    }
}
