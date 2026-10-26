<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Module 2 — Gestion des catégories (EF6). */
class CategorieTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function donnees(array $surcharge = []): array
    {
        return array_merge(['nom' => 'Cadeaux', 'description' => 'Fêtes et anniversaires', 'type' => 'depense'], $surcharge);
    }

    // ---------- Catégories par défaut ----------

    public function test_l_inscription_cree_les_10_categories_par_defaut(): void
    {
        $this->post('/register', [
            'nom' => 'Sy', 'prenom' => 'Ali', 'email' => 'ali@example.com',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ]);

        $ali = User::where('email', 'ali@example.com')->firstOrFail();
        $this->assertSame(10, $ali->categories()->where('type', 'depense')->count());
        $this->assertSame(3, $ali->categories()->where('type', 'revenu')->count());

        $this->assertEqualsCanonicalizing(
            ['Alimentation', 'Transport', 'Logement', 'Santé', 'Éducation', "Facture d'électricité", "Facture d'eau", "Facture d'internet", 'Facture globale', 'Loisirs'],
            $ali->categories()->where('type', 'depense')->pluck('nom')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['Salaire', 'Activité commerciale', 'Autres revenus'],
            $ali->categories()->where('type', 'revenu')->pluck('nom')->all()
        );
    }

    public function test_la_migration_ajoute_les_categories_de_factures_aux_comptes_existants_sans_rien_perdre(): void
    {
        $ancien = User::factory()->create();
        $ancien->categories()->create(['nom' => 'Factures', 'type' => 'depense']);
        $ancien->categories()->create(['nom' => 'Loisirs', 'type' => 'depense']);
        $admin = User::factory()->admin()->create();
        $facture = Categorie::where('nom', 'Factures')->firstOrFail();
        Depense::factory()->create(['user_id' => $ancien->id, 'categorie_id' => $facture->id]);

        $migration = require database_path('migrations/2026_10_16_090000_ajouter_categories_factures_aux_comptes_existants.php');
        $migration->up();
        $migration->up(); // rejouable sans doublon

        $this->assertEqualsCanonicalizing(
            ['Factures', 'Loisirs', "Facture d'électricité", "Facture d'eau", "Facture d'internet", 'Facture globale'],
            $ancien->categories()->pluck('nom')->all()
        );
        $this->assertSame(1, $facture->fresh()->depenses()->count(), 'la catégorie « Factures » et ses transactions sont conservées');
        $this->assertSame(0, $admin->categories()->count(), "l'administrateur n'a pas de catégories");
        $this->assertSame('Eau, électricité et internet réunis', $ancien->categories()->where('nom', 'Facture globale')->value('description'));
    }

    public function test_la_migration_remplace_factures_par_facture_globale_sans_perdre_de_depense(): void
    {
        $a = User::factory()->create();
        $a->categories()->create(['nom' => 'Factures', 'type' => 'depense']);
        $b = User::factory()->create();
        $b->categories()->create(['nom' => 'Factures', 'type' => 'depense']);
        $b->categories()->create(['nom' => 'Facture globale', 'type' => 'depense']);
        $autre = User::factory()->create();
        $salaire = $autre->categories()->create(['nom' => 'Salaire', 'type' => 'revenu']);

        foreach ([$a, $b] as $user) {
            Depense::factory()->count(2)->create([
                'user_id' => $user->id,
                'categorie_id' => $user->categories()->where('nom', 'Factures')->value('id'),
                'montant' => 10000,
            ]);
        }

        $migration = require database_path('migrations/2026_10_16_100000_remplacer_la_categorie_factures_par_facture_globale.php');
        $migration->up();
        $migration->up(); // rejouable : plus rien à faire

        $this->assertSame(0, Categorie::where('nom', 'Factures')->count(), 'plus aucune catégorie « Factures »');
        foreach ([$a, $b] as $user) {
            $globale = $user->categories()->where('nom', 'Facture globale')->firstOrFail();
            $this->assertSame(2, $globale->depenses()->count(), 'les dépenses sont passées sur « Facture globale »');
            $this->assertSame(2, Depense::where('user_id', $user->id)->count(), 'aucune dépense perdue');
        }
        $this->assertSame(1, $b->categories()->where('nom', 'Facture globale')->count(), 'pas de doublon si « Facture globale » existait déjà');
        $this->assertTrue($salaire->fresh()->exists, 'les autres catégories ne sont pas touchées');
    }

    public function test_une_inscription_refusee_ne_cree_aucune_categorie(): void
    {
        $this->post('/register', ['nom' => 'Sy']);

        $this->assertSame(0, Categorie::count());
    }

    public function test_les_categories_par_defaut_sont_propres_a_chaque_utilisateur(): void
    {
        $autre = User::factory()->create();
        Categorie::creerParDefaut($this->user);
        Categorie::creerParDefaut($autre);

        $this->assertSame(13, $this->user->categories()->count());
        $this->assertSame(13, $autre->categories()->count());
        $this->assertSame(26, Categorie::count());
    }

    public function test_creer_les_categories_par_defaut_est_rejouable(): void
    {
        Categorie::creerParDefaut($this->user);
        Categorie::creerParDefaut($this->user);

        $this->assertSame(13, $this->user->categories()->count());
    }

    public function test_le_seeder_cree_les_categories_des_utilisateurs_mais_pas_de_l_admin(): void
    {
        $this->seed();

        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertSame(0, $admin->categories()->count());
        foreach (['awa@smartbudget.sn', 'moussa@smartbudget.sn'] as $email) {
            $this->assertSame(13, User::where('email', $email)->firstOrFail()->categories()->count(), $email);
        }
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get('/categories')->assertRedirect(route('login'));
        $this->post('/categories', $this->donnees())->assertRedirect(route('login'));
    }

    public function test_l_administrateur_n_a_pas_acces_aux_categories(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/categories')->assertForbidden();
        $this->actingAs($admin)->post('/categories', $this->donnees())->assertForbidden();
        $this->assertSame(0, Categorie::count());
    }

    public function test_un_utilisateur_suspendu_est_deconnecte(): void
    {
        $this->actingAs(User::factory()->suspendu()->create())->get('/categories')->assertRedirect(route('login'));
    }

    // ---------- Liste et recherche ----------

    public function test_la_liste_affiche_deux_blocs_dépenses_et_revenus(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Cantine']);
        Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Primes']);

        $this->actingAs($this->user)->get('/categories')
            ->assertOk()
            ->assertSee('Catégories de dépenses')
            ->assertSee('Catégories de revenus')
            ->assertSee('Cantine')
            ->assertSee('Primes');
    }

    public function test_la_liste_n_affiche_que_les_categories_de_l_utilisateur(): void
    {
        $autre = User::factory()->create();
        Categorie::factory()->for($autre)->create(['nom' => 'SecretDeLAutre']);
        Categorie::factory()->for($this->user)->create(['nom' => 'MaCategorie']);

        $this->actingAs($this->user)->get('/categories')
            ->assertSee('MaCategorie')
            ->assertDontSee('SecretDeLAutre');
    }

    public function test_la_recherche_filtre_par_nom(): void
    {
        Categorie::creerParDefaut($this->user);

        $this->actingAs($this->user)->get('/categories?q=alim')
            ->assertOk()
            ->assertSee('Alimentation')
            ->assertDontSee('Transport');
    }

    public function test_la_recherche_sans_resultat_affiche_un_message(): void
    {
        Categorie::creerParDefaut($this->user);

        $this->actingAs($this->user)->get('/categories?q=zzzzz')
            ->assertOk()
            ->assertSee('Aucune catégorie trouvée.');
    }

    public function test_la_recherche_ne_traverse_pas_les_donnees_des_autres(): void
    {
        $autre = User::factory()->create();
        Categorie::factory()->for($autre)->create(['nom' => 'Voyage']);

        $this->actingAs($this->user)->get('/categories?q=Voyage')
            ->assertSee('Aucune catégorie trouvée.')
            ->assertDontSee('<td class="fw-medium">Voyage</td>', false);
    }

    public function test_la_liste_vide_invite_a_ajouter(): void
    {
        $this->actingAs($this->user)->get('/categories')->assertOk()->assertSee('Aucune catégorie.');
    }

    public function test_la_liste_affiche_le_nombre_de_transactions_par_categorie(): void
    {
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Cantine']);
        Depense::factory()->count(3)->create(['user_id' => $this->user->id, 'categorie_id' => $cat->id]);

        $html = $this->actingAs($this->user)->get('/categories')->getContent();

        $this->assertMatchesRegularExpression('/Cantine<\/td>.*?text-center">3</s', $html);
    }

    // ---------- Ajout ----------

    public function test_ajout_d_une_categorie(): void
    {
        $this->actingAs($this->user)->post('/categories', $this->donnees())
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Catégorie ajoutée avec succès.');

        $this->assertDatabaseHas('categories', [
            'user_id' => $this->user->id, 'nom' => 'Cadeaux', 'type' => 'depense', 'description' => 'Fêtes et anniversaires',
        ]);
    }

    public function test_ajout_d_une_categorie_de_revenu_sans_description(): void
    {
        $this->actingAs($this->user)->post('/categories', ['nom' => 'Freelance', 'type' => 'revenu'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['nom' => 'Freelance', 'type' => 'revenu', 'description' => null]);
    }

    public function test_l_ajout_ignore_un_user_id_envoye_dans_le_formulaire(): void
    {
        $autre = User::factory()->create();

        $this->actingAs($this->user)->post('/categories', $this->donnees(['user_id' => $autre->id]));

        $cat = Categorie::where('nom', 'Cadeaux')->firstOrFail();
        $this->assertSame($this->user->id, $cat->user_id);
    }

    public function test_ajout_champs_obligatoires(): void
    {
        $this->actingAs($this->user)->post('/categories', [])->assertSessionHasErrors(['nom', 'type']);
        $this->assertSame(0, Categorie::count());
    }

    public function test_ajout_type_invalide(): void
    {
        $this->actingAs($this->user)->post('/categories', $this->donnees(['type' => 'cadeau']))
            ->assertSessionHasErrors('type');
    }

    public function test_ajout_nom_trop_long(): void
    {
        $this->actingAs($this->user)->post('/categories', $this->donnees(['nom' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('nom');
    }

    public function test_ajout_nom_de_100_caracteres_accepte(): void
    {
        $this->actingAs($this->user)->post('/categories', $this->donnees(['nom' => str_repeat('a', 100)]))
            ->assertSessionHasNoErrors();
    }

    public function test_ajout_description_trop_longue(): void
    {
        $this->actingAs($this->user)->post('/categories', $this->donnees(['description' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('description');
    }

    public function test_ajout_nom_en_double_pour_le_meme_type_est_refuse(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Cadeaux', 'type' => 'depense']);

        $this->actingAs($this->user)->post('/categories', $this->donnees())
            ->assertSessionHasErrors(['nom' => 'Vous avez déjà une catégorie portant ce nom pour ce type.']);
        $this->assertSame(1, Categorie::count());
    }

    public function test_ajout_meme_nom_pour_un_autre_type_est_accepte(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Cadeaux', 'type' => 'depense']);

        $this->actingAs($this->user)->post('/categories', $this->donnees(['type' => 'revenu']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Categorie::count());
    }

    public function test_ajout_meme_nom_chez_un_autre_utilisateur_est_accepte(): void
    {
        Categorie::factory()->for(User::factory()->create())->create(['nom' => 'Cadeaux', 'type' => 'depense']);

        $this->actingAs($this->user)->post('/categories', $this->donnees())->assertSessionHasNoErrors();
        $this->assertSame(2, Categorie::count());
    }

    public function test_une_erreur_de_validation_reaffiche_la_modale_avec_le_message(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Cadeaux', 'type' => 'depense']);

        $this->actingAs($this->user)->from('/categories')->post('/categories', $this->donnees());
        $reponse = $this->actingAs($this->user)->get('/categories');

        $reponse->assertSee('Vous avez déjà une catégorie portant ce nom pour ce type.');
    }

    // ---------- Modification ----------

    public function test_modification_d_une_categorie(): void
    {
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Ancien', 'type' => 'depense']);

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => 'Nouveau', 'description' => 'Desc'])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Catégorie modifiée avec succès.');

        $this->assertDatabaseHas('categories', ['id' => $cat->id, 'nom' => 'Nouveau', 'description' => 'Desc']);
    }

    public function test_le_type_d_une_categorie_ne_se_modifie_pas(): void
    {
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Ancien', 'type' => 'depense']);

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => 'Ancien', 'type' => 'revenu']);

        $this->assertSame('depense', $cat->fresh()->type);
    }

    public function test_modification_en_gardant_son_propre_nom_est_acceptee(): void
    {
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Meme', 'type' => 'depense']);

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => 'Meme', 'description' => 'Autre'])
            ->assertSessionHasNoErrors();
    }

    public function test_modification_vers_un_nom_deja_pris_est_refusee(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Pris', 'type' => 'depense']);
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Libre', 'type' => 'depense']);

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => 'Pris'])
            ->assertSessionHasErrors('nom');
        $this->assertSame('Libre', $cat->fresh()->nom);
    }

    public function test_modification_nom_obligatoire(): void
    {
        $cat = Categorie::factory()->for($this->user)->create();

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => ''])->assertSessionHasErrors('nom');
    }

    public function test_modifier_la_categorie_d_un_autre_utilisateur_donne_403(): void
    {
        $autre = User::factory()->create();
        $cat = Categorie::factory()->for($autre)->create(['nom' => 'Protege']);

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => 'Pirate'])->assertForbidden();
        $this->assertSame('Protege', $cat->fresh()->nom);
    }

    public function test_modifier_la_categorie_d_un_autre_avec_des_donnees_invalides_donne_quand_meme_403(): void
    {
        $cat = Categorie::factory()->for(User::factory()->create())->create();

        $this->actingAs($this->user)->put("/categories/{$cat->id}", ['nom' => ''])->assertForbidden();
    }

    public function test_modifier_une_categorie_inexistante_donne_404(): void
    {
        $this->actingAs($this->user)->put('/categories/9999', ['nom' => 'X'])->assertNotFound();
    }

    // ---------- Suppression ----------

    public function test_suppression_d_une_categorie_inutilisee(): void
    {
        $cat = Categorie::factory()->for($this->user)->create();

        $this->actingAs($this->user)->delete("/categories/{$cat->id}")
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Catégorie supprimée avec succès.');

        $this->assertModelMissing($cat);
    }

    /** T6 */
    public function test_t6_suppression_d_une_categorie_utilisee_par_une_depense_est_refusee(): void
    {
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation']);
        Depense::factory()->count(2)->create(['user_id' => $this->user->id, 'categorie_id' => $cat->id]);

        $reponse = $this->actingAs($this->user)->delete("/categories/{$cat->id}");

        $reponse->assertRedirect(route('categories.index'));
        $reponse->assertSessionHas('error');
        $this->assertStringContainsString('utilisée par 2 transactions', session('error'));
        $this->assertModelExists($cat);
    }

    public function test_suppression_d_une_categorie_utilisee_par_un_revenu_est_refusee(): void
    {
        $cat = Categorie::factory()->for($this->user)->revenu()->create(['nom' => 'Salaire']);
        Revenu::factory()->create(['user_id' => $this->user->id, 'categorie_id' => $cat->id]);

        $this->actingAs($this->user)->delete("/categories/{$cat->id}");

        $this->assertStringContainsString('utilisée par 1 transaction.', session('error'));
        $this->assertModelExists($cat);
    }

    public function test_le_message_de_refus_s_affiche_sur_la_page(): void
    {
        $cat = Categorie::factory()->for($this->user)->create(['nom' => 'Alimentation']);
        Depense::factory()->create(['user_id' => $this->user->id, 'categorie_id' => $cat->id]);

        $this->actingAs($this->user)->followingRedirects()->delete("/categories/{$cat->id}")
            ->assertSee('Impossible de supprimer la catégorie');
    }

    public function test_supprimer_la_categorie_d_un_autre_utilisateur_donne_403(): void
    {
        $cat = Categorie::factory()->for(User::factory()->create())->create();

        $this->actingAs($this->user)->delete("/categories/{$cat->id}")->assertForbidden();
        $this->assertModelExists($cat);
    }

    public function test_l_administrateur_ne_peut_pas_supprimer_une_categorie(): void
    {
        $cat = Categorie::factory()->for($this->user)->create();

        $this->actingAs(User::factory()->admin()->create())->delete("/categories/{$cat->id}")->assertForbidden();
        $this->assertModelExists($cat);
    }

    // ---------- Base de données ----------

    public function test_l_index_unique_user_nom_type_est_applique_par_la_base(): void
    {
        Categorie::factory()->for($this->user)->create(['nom' => 'Doublon', 'type' => 'depense']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Categorie::factory()->for($this->user)->create(['nom' => 'Doublon', 'type' => 'depense']);
    }

    public function test_la_base_interdit_de_supprimer_une_categorie_utilisee_restrict(): void
    {
        $cat = Categorie::factory()->for($this->user)->create();
        Depense::factory()->create(['user_id' => $this->user->id, 'categorie_id' => $cat->id]);

        $this->expectException(QueryException::class);
        DB::table('categories')->where('id', $cat->id)->delete();
    }

    public function test_les_categories_d_un_utilisateur_sont_supprimees_avec_lui_cascade(): void
    {
        Categorie::factory()->count(3)->for($this->user)->create();

        $this->user->delete();

        $this->assertSame(0, Categorie::count());
    }

    public function test_les_montants_sont_stockes_en_decimal(): void
    {
        $depense = Depense::factory()->create(['user_id' => $this->user->id, 'montant' => 5000]);

        $this->assertSame('5000.00', $depense->fresh()->montant);
        $this->assertSame('2026-01-15', Depense::factory()->create(['user_id' => $this->user->id, 'date' => '2026-01-15'])->fresh()->date->format('Y-m-d'));
    }

    public function test_les_relations_eloquent(): void
    {
        $cat = Categorie::factory()->for($this->user)->create();
        $depense = Depense::factory()->create(['user_id' => $this->user->id, 'categorie_id' => $cat->id]);

        $this->assertTrue($cat->user->is($this->user));
        $this->assertTrue($cat->depenses->first()->is($depense));
        $this->assertTrue($depense->categorie->is($cat));
        $this->assertTrue($depense->user->is($this->user));
        $this->assertTrue($this->user->categories->contains($cat));
        $this->assertTrue($this->user->depenses->contains($depense));
        $this->assertTrue($cat->estUtilisee());
    }
}
