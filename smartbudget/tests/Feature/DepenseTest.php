<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module 3 — Gestion des dépenses (EF5). */
class DepenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categorie $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cat = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation', 'type' => 'depense']);
    }

    private function depense(array $attrs = [], ?User $user = null, ?Categorie $cat = null): Depense
    {
        $user ??= $this->user;
        $cat ??= $user->is($this->user) ? $this->cat : Categorie::factory()->for($user)->create();

        return Depense::factory()->create(array_merge(
            ['user_id' => $user->id, 'categorie_id' => $cat->id, 'montant' => 1000, 'date' => '2026-10-01', 'description' => 'Courses'],
            $attrs
        ));
    }

    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'montant' => '5000',
            'date' => now()->format('Y-m-d'),
            'categorie_id' => $this->cat->id,
            'description' => 'Marché',
        ], $surcharge);
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $d = $this->depense();

        $this->get('/depenses')->assertRedirect(route('login'));
        $this->get('/depenses/create')->assertRedirect(route('login'));
        $this->post('/depenses', $this->donnees())->assertRedirect(route('login'));
        $this->get("/depenses/{$d->id}/edit")->assertRedirect(route('login'));
        $this->put("/depenses/{$d->id}", $this->donnees())->assertRedirect(route('login'));
        $this->delete("/depenses/{$d->id}")->assertRedirect(route('login'));
    }

    public function test_l_administrateur_n_a_acces_a_aucune_route_de_depenses(): void
    {
        $admin = User::factory()->admin()->create();
        $d = $this->depense();

        $this->actingAs($admin)->get('/depenses')->assertForbidden();
        $this->actingAs($admin)->get('/depenses/create')->assertForbidden();
        $this->actingAs($admin)->post('/depenses', $this->donnees())->assertForbidden();
        $this->actingAs($admin)->get("/depenses/{$d->id}/edit")->assertForbidden();
        $this->actingAs($admin)->delete("/depenses/{$d->id}")->assertForbidden();
        $this->assertModelExists($d);
    }

    // ---------- Liste ----------

    public function test_la_liste_affiche_montant_formate_et_date_jj_mm_aaaa(): void
    {
        $this->depense(['montant' => 150000, 'date' => '2026-03-05']);

        $this->actingAs($this->user)->get('/depenses')
            ->assertOk()
            ->assertSee('150 000 FCFA')
            ->assertSee('05/03/2026')
            ->assertSee('Alimentation')
            ->assertSee('Courses');
    }

    public function test_la_liste_affiche_les_decimales_quand_il_y_en_a(): void
    {
        $this->depense(['montant' => 1500.5]);

        $this->actingAs($this->user)->get('/depenses')->assertSee('1 500,50 FCFA');
    }

    public function test_la_liste_est_triee_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        $this->depense(['description' => 'ANCIENNE', 'date' => '2026-01-01']);
        $this->depense(['description' => 'RECENTE', 'date' => '2026-09-01']);
        $this->depense(['description' => 'MILIEU', 'date' => '2026-05-01']);

        $this->actingAs($this->user)->get('/depenses')->assertSeeInOrder(['RECENTE', 'MILIEU', 'ANCIENNE']);
    }

    public function test_la_liste_n_affiche_que_les_depenses_de_l_utilisateur(): void
    {
        $autre = User::factory()->create();
        $this->depense(['description' => 'MaDepense']);
        $this->depense(['description' => 'DepenseDeLAutre'], $autre);

        $this->actingAs($this->user)->get('/depenses')
            ->assertSee('MaDepense')
            ->assertDontSee('DepenseDeLAutre');
    }

    public function test_la_liste_est_paginee_par_10(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->depense(['description' => sprintf('LIGNE%02d', $i), 'date' => '2026-06-'.sprintf('%02d', $i)]);
        }

        $page1 = $this->actingAs($this->user)->get('/depenses');
        $page1->assertSee('LIGNE25')->assertSee('LIGNE16')->assertDontSee('LIGNE15');
        $this->assertSame(10, substr_count($page1->getContent(), 'data-confirm="Supprimer cette dépense'));

        $this->actingAs($this->user)->get('/depenses?page=3')->assertSee('LIGNE05')->assertSee('LIGNE01')->assertDontSee('LIGNE06');
        $page1->assertSee('page-link', false);
    }

    public function test_la_liste_vide_invite_a_saisir_une_premiere_transaction(): void
    {
        $this->actingAs($this->user)->get('/depenses')
            ->assertOk()
            ->assertSee('Aucune dépense enregistrée. Commencez par saisir votre première transaction.');
    }

    public function test_la_liste_affiche_le_total(): void
    {
        $this->depense(['montant' => 2000]);
        $this->depense(['montant' => 3500]);

        $this->actingAs($this->user)->get('/depenses')->assertSee('Total : 5 500 FCFA');
    }

    // ---------- Recherche et filtres ----------

    public function test_recherche_par_mot_cle_dans_la_description(): void
    {
        $this->depense(['description' => 'Loyer octobre']);
        $this->depense(['description' => 'Courses marché']);

        $this->actingAs($this->user)->get('/depenses?q=loyer')
            ->assertSee('Loyer octobre')
            ->assertDontSee('Courses marché');
    }

    public function test_la_recherche_traite_pourcent_et_underscore_comme_du_texte(): void
    {
        $this->depense(['description' => 'Remise 50% été']);
        $this->depense(['description' => 'Autre chose']);

        $this->actingAs($this->user)->get('/depenses?q='.urlencode('%'))
            ->assertSee('Remise 50% été')
            ->assertDontSee('Autre chose');
    }

    public function test_filtre_par_categorie(): void
    {
        $transport = Categorie::factory()->for($this->user)->create(['nom' => 'Transport', 'type' => 'depense']);
        $this->depense(['description' => 'Taxi'], $this->user, $transport);
        $this->depense(['description' => 'Pain']);

        $this->actingAs($this->user)->get('/depenses?categorie_id='.$transport->id)
            ->assertSee('Taxi')
            ->assertDontSee('Pain');
    }

    public function test_filtre_par_intervalle_de_dates_bornes_incluses(): void
    {
        $this->depense(['description' => 'AVANT', 'date' => '2026-04-30']);
        $this->depense(['description' => 'DEBUT', 'date' => '2026-05-01']);
        $this->depense(['description' => 'FIN', 'date' => '2026-05-31']);
        $this->depense(['description' => 'APRES', 'date' => '2026-06-01']);

        $this->actingAs($this->user)->get('/depenses?date_debut=2026-05-01&date_fin=2026-05-31')
            ->assertSee('DEBUT')->assertSee('FIN')
            ->assertDontSee('AVANT')->assertDontSee('APRES');
    }

    public function test_filtre_par_montant_min_et_max_bornes_incluses(): void
    {
        $this->depense(['description' => 'PETIT', 'montant' => 999]);
        $this->depense(['description' => 'BORNEMIN', 'montant' => 1000]);
        $this->depense(['description' => 'BORNEMAX', 'montant' => 5000]);
        $this->depense(['description' => 'GROS', 'montant' => 5001]);

        $this->actingAs($this->user)->get('/depenses?montant_min=1000&montant_max=5000')
            ->assertSee('BORNEMIN')->assertSee('BORNEMAX')
            ->assertDontSee('PETIT')->assertDontSee('GROS');
    }

    public function test_les_filtres_se_combinent(): void
    {
        $transport = Categorie::factory()->for($this->user)->create(['nom' => 'Transport', 'type' => 'depense']);
        $this->depense(['description' => 'Taxi aéroport', 'montant' => 8000, 'date' => '2026-05-10'], $this->user, $transport);
        $this->depense(['description' => 'Taxi ville', 'montant' => 1000, 'date' => '2026-05-10'], $this->user, $transport);
        $this->depense(['description' => 'Taxi ancien', 'montant' => 8000, 'date' => '2025-01-10'], $this->user, $transport);
        $this->depense(['description' => 'Taxi mais autre catégorie', 'montant' => 8000, 'date' => '2026-05-10']);

        $this->actingAs($this->user)->get('/depenses?'.http_build_query([
            'q' => 'taxi', 'categorie_id' => $transport->id, 'date_debut' => '2026-01-01', 'montant_min' => 5000,
        ]))
            ->assertSee('Taxi aéroport')
            ->assertDontSee('Taxi ville')
            ->assertDontSee('Taxi ancien')
            ->assertDontSee('Taxi mais autre catégorie');
    }

    /** T9 (partie dépenses) */
    public function test_t9_recherche_sans_resultat_affiche_aucune_transaction_trouvee(): void
    {
        $this->depense();

        $this->actingAs($this->user)->get('/depenses?q=introuvable')
            ->assertOk()
            ->assertSee('Aucune transaction trouvée.');
    }

    public function test_le_total_suit_les_filtres(): void
    {
        $this->depense(['description' => 'A', 'montant' => 2000]);
        $this->depense(['description' => 'B', 'montant' => 3500]);

        $this->actingAs($this->user)->get('/depenses?q=B')->assertSee('Total des résultats : 3 500 FCFA');
    }

    public function test_la_recherche_ne_voit_pas_les_depenses_des_autres(): void
    {
        $this->depense(['description' => 'SecretAutre'], User::factory()->create());

        $this->actingAs($this->user)->get('/depenses?q=SecretAutre')->assertSee('Aucune transaction trouvée.');
    }

    public function test_filtre_invalide_est_ignore_avec_un_message(): void
    {
        $this->depense(['description' => 'Visible']);

        $this->actingAs($this->user)->get('/depenses?date_debut=pas-une-date')
            ->assertOk()
            ->assertSee('Visible')
            ->assertSee('date de début');
    }

    public function test_intervalle_de_dates_inverse_affiche_un_message(): void
    {
        $this->depense(['description' => 'Visible', 'date' => '2026-05-10']);

        $this->actingAs($this->user)->get('/depenses?date_debut=2026-06-01&date_fin=2026-05-01')
            ->assertOk()
            ->assertSee('Visible')
            ->assertSee('La date de fin doit être postérieure à la date de début.');
    }

    public function test_intervalle_de_montants_inverse_affiche_un_message(): void
    {
        $this->depense(['description' => 'Visible']);

        $this->actingAs($this->user)->get('/depenses?montant_min=9000&montant_max=100')
            ->assertSee('Visible')
            ->assertSee('Le montant maximum doit être supérieur au montant minimum.');
    }

    public function test_les_filtres_sont_conserves_dans_les_liens_de_pagination(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->depense(['description' => "Taxi $i"]);
        }

        $this->actingAs($this->user)->get('/depenses?q=Taxi')->assertSee('q=Taxi&amp;page=2', false);
    }

    // ---------- Formulaire d'ajout ----------

    public function test_le_formulaire_propose_uniquement_les_categories_de_depense_de_l_utilisateur(): void
    {
        Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Salaire']);
        Categorie::factory()->for(User::factory()->create())->create(['nom' => 'CategorieDeLAutre']);

        $this->actingAs($this->user)->get('/depenses/create')
            ->assertOk()
            ->assertSee('Alimentation')
            ->assertDontSee('Salaire')
            ->assertDontSee('CategorieDeLAutre')
            ->assertSee('value="'.now()->format('Y-m-d').'"', false);
    }

    public function test_le_formulaire_accorde_l_adjectif_nouvelle_depense(): void
    {
        $this->actingAs($this->user)->get('/depenses/create')
            ->assertSee('Nouvelle dépense')
            ->assertDontSee('Nouveau dépense');
    }

    public function test_le_formulaire_sans_categorie_invite_a_en_creer_une(): void
    {
        $this->cat->delete();

        $this->actingAs($this->user)->get('/depenses/create')
            ->assertSee('Vous n\'avez encore aucune catégorie de dépense.', false)
            ->assertSee(route('categories.index'), false);
    }

    // ---------- Ajout (T4, T5) ----------

    /** T4 */
    public function test_t4_ajout_d_une_depense_de_5000_fcfa(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '5000']))
            ->assertRedirect(route('depenses.index'))
            ->assertSessionHas('success', 'Dépense ajoutée avec succès.');

        $this->assertDatabaseHas('depenses', [
            'user_id' => $this->user->id, 'categorie_id' => $this->cat->id, 'montant' => '5000.00', 'description' => 'Marché',
        ]);
        $this->actingAs($this->user)->get('/depenses')->assertSee('5 000 FCFA');
    }

    public function test_ajout_sans_description(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['description' => '']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('depenses', ['description' => null]);
    }

    public function test_ajout_avec_centimes(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '1500.50']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('depenses', ['montant' => '1500.50']);
    }

    public function test_ajout_montant_minimum_0_01_accepte(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '0.01']))->assertSessionHasNoErrors();
    }

    /** T5 */
    public function test_t5_ajout_avec_montant_negatif_est_refuse(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '-500']))
            ->assertSessionHasErrors(['montant' => 'Le montant doit être strictement positif.']);
        $this->assertSame(0, Depense::count());
    }

    /** T5 */
    public function test_t5_ajout_avec_montant_vide_est_refuse(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '']))
            ->assertSessionHasErrors('montant');
        $this->assertSame(0, Depense::count());
    }

    public function test_ajout_montant_zero_refuse(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '0']))
            ->assertSessionHasErrors('montant');
    }

    public function test_ajout_montant_non_numerique_refuse(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => 'abc']))
            ->assertSessionHasErrors('montant');
    }

    public function test_ajout_montant_avec_plus_de_2_decimales_refuse(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '10.001']))
            ->assertSessionHasErrors('montant');
    }

    public function test_ajout_montant_trop_grand_refuse(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['montant' => '10000000000']))
            ->assertSessionHasErrors('montant');
    }

    public function test_ajout_date_obligatoire(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['date' => '']))->assertSessionHasErrors('date');
    }

    public function test_ajout_date_dans_le_futur_refusee(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('date');
        $this->assertSame(0, Depense::count());
    }

    public function test_ajout_date_d_aujourd_hui_acceptee(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['date' => now()->format('Y-m-d')]))
            ->assertSessionHasNoErrors();
    }

    public function test_ajout_date_invalide_refusee(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['date' => '2026-13-45']))->assertSessionHasErrors('date');
    }

    public function test_ajout_categorie_obligatoire(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['categorie_id' => '']))
            ->assertSessionHasErrors(['categorie_id' => 'Veuillez choisir une catégorie.']);
    }

    public function test_ajout_categorie_d_un_autre_utilisateur_refusee(): void
    {
        $autre = Categorie::factory()->for(User::factory()->create())->create();

        $this->actingAs($this->user)->post('/depenses', $this->donnees(['categorie_id' => $autre->id]))
            ->assertSessionHasErrors('categorie_id');
        $this->assertSame(0, Depense::count());
    }

    public function test_ajout_categorie_de_type_revenu_refusee(): void
    {
        $revenu = Categorie::factory()->for($this->user)->revenu()->create();

        $this->actingAs($this->user)->post('/depenses', $this->donnees(['categorie_id' => $revenu->id]))
            ->assertSessionHasErrors('categorie_id');
        $this->assertSame(0, Depense::count());
    }

    public function test_ajout_categorie_inexistante_refusee(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['categorie_id' => 99999]))
            ->assertSessionHasErrors('categorie_id');
    }

    public function test_ajout_description_trop_longue_refusee(): void
    {
        $this->actingAs($this->user)->post('/depenses', $this->donnees(['description' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('description');
    }

    public function test_ajout_ignore_un_user_id_envoye_dans_le_formulaire(): void
    {
        $autre = User::factory()->create();

        $this->actingAs($this->user)->post('/depenses', $this->donnees(['user_id' => $autre->id]));

        $this->assertSame($this->user->id, Depense::firstOrFail()->user_id);
    }

    public function test_une_erreur_de_validation_conserve_les_valeurs_saisies(): void
    {
        $this->actingAs($this->user)->from('/depenses/create')->post('/depenses', $this->donnees(['montant' => '-5', 'description' => 'Garde-moi']))
            ->assertRedirect('/depenses/create');

        $this->actingAs($this->user)->get('/depenses/create')
            ->assertSee('Garde-moi')
            ->assertSee('Le montant doit être strictement positif.');
    }

    // ---------- Modification ----------

    public function test_le_formulaire_de_modification_est_prerempli(): void
    {
        $d = $this->depense(['montant' => 7500, 'date' => '2026-02-14', 'description' => 'Cadeau']);

        $this->actingAs($this->user)->get("/depenses/{$d->id}/edit")
            ->assertOk()
            ->assertSee('value="7500.00"', false)
            ->assertSee('value="2026-02-14"', false)
            ->assertSee('value="Cadeau"', false);
    }

    public function test_modification_d_une_depense(): void
    {
        $d = $this->depense();
        $transport = Categorie::factory()->for($this->user)->create(['nom' => 'Transport', 'type' => 'depense']);

        $this->actingAs($this->user)->put("/depenses/{$d->id}", $this->donnees([
            'montant' => '9000', 'date' => '2026-08-20', 'categorie_id' => $transport->id, 'description' => 'Modifiée',
        ]))
            ->assertRedirect(route('depenses.index'))
            ->assertSessionHas('success', 'Dépense modifiée avec succès.');

        $this->assertDatabaseHas('depenses', [
            'id' => $d->id, 'montant' => '9000.00', 'date' => '2026-08-20 00:00:00', 'categorie_id' => $transport->id, 'description' => 'Modifiée',
        ]);
    }

    public function test_modification_avec_donnees_invalides_ne_change_rien(): void
    {
        $d = $this->depense(['montant' => 1000]);

        $this->actingAs($this->user)->put("/depenses/{$d->id}", $this->donnees(['montant' => '-1']))
            ->assertSessionHasErrors('montant');

        $this->assertSame('1000.00', $d->fresh()->montant);
    }

    public function test_la_modification_ne_change_pas_le_proprietaire(): void
    {
        $d = $this->depense();
        $autre = User::factory()->create();

        $this->actingAs($this->user)->put("/depenses/{$d->id}", $this->donnees(['user_id' => $autre->id]));

        $this->assertSame($this->user->id, $d->fresh()->user_id);
    }

    public function test_modification_vers_la_categorie_d_un_autre_est_refusee(): void
    {
        $d = $this->depense();
        $autre = Categorie::factory()->for(User::factory()->create())->create();

        $this->actingAs($this->user)->put("/depenses/{$d->id}", $this->donnees(['categorie_id' => $autre->id]))
            ->assertSessionHasErrors('categorie_id');
        $this->assertSame($this->cat->id, $d->fresh()->categorie_id);
    }

    public function test_modification_d_une_depense_inexistante_donne_404(): void
    {
        $this->actingAs($this->user)->get('/depenses/9999/edit')->assertNotFound();
        $this->actingAs($this->user)->put('/depenses/9999', $this->donnees())->assertNotFound();
        $this->actingAs($this->user)->delete('/depenses/9999')->assertNotFound();
    }

    // ---------- T7 : isolation entre utilisateurs ----------

    /** T7 */
    public function test_t7_acceder_a_la_depense_d_un_autre_utilisateur_donne_403(): void
    {
        $autre = User::factory()->create();
        $d = $this->depense(['description' => 'Privee', 'montant' => 1000], $autre);

        $this->actingAs($this->user)->get("/depenses/{$d->id}/edit")->assertForbidden();
        $this->actingAs($this->user)->put("/depenses/{$d->id}", $this->donnees(['montant' => '1']))->assertForbidden();
        $this->actingAs($this->user)->delete("/depenses/{$d->id}")->assertForbidden();

        $d->refresh();
        $this->assertSame('1000.00', $d->montant);
        $this->assertSame('Privee', $d->description);
    }

    public function test_t7_meme_avec_des_donnees_invalides_la_modification_d_un_autre_donne_403(): void
    {
        $d = $this->depense([], User::factory()->create());

        $this->actingAs($this->user)->put("/depenses/{$d->id}", ['montant' => ''])->assertForbidden();
    }

    // ---------- Suppression ----------

    public function test_suppression_d_une_depense(): void
    {
        $d = $this->depense();

        $this->actingAs($this->user)->delete("/depenses/{$d->id}")
            ->assertRedirect(route('depenses.index'))
            ->assertSessionHas('success', 'Dépense supprimée avec succès.');

        $this->assertModelMissing($d);
    }

    public function test_la_suppression_demande_une_confirmation_dans_l_interface(): void
    {
        $this->depense(['montant' => 5000]);

        $this->actingAs($this->user)->get('/depenses')
            ->assertSee('data-confirm="Supprimer cette dépense de 5 000 FCFA ?"', false);
    }

    public function test_supprimer_une_depense_libere_la_categorie(): void
    {
        $d = $this->depense();
        $this->assertTrue($this->cat->estUtilisee());

        $this->actingAs($this->user)->delete("/depenses/{$d->id}");

        $this->assertFalse($this->cat->fresh()->estUtilisee());
    }

    /**
     * Constat à traiter au Module 8 : avec les contraintes du CDC (CASCADE sur user_id, RESTRICT sur
     * categorie_id), supprimer directement un utilisateur qui a des transactions échoue, car la cascade
     * vers ses catégories bute sur le RESTRICT. L'administration devra supprimer ses transactions d'abord.
     */
    public function test_supprimer_directement_un_utilisateur_avec_depenses_est_bloque_par_restrict(): void
    {
        $this->depense();

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->user->delete();
    }

    public function test_supprimer_les_transactions_puis_l_utilisateur_nettoie_tout(): void
    {
        $this->depense();
        $this->depense(['montant' => 2000]);

        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->user->depenses()->delete();
            $this->user->revenus()->delete();
            $this->user->delete();
        });

        $this->assertSame(0, Depense::count());
        $this->assertSame(0, Categorie::count());
        $this->assertModelMissing($this->user);
    }
}
