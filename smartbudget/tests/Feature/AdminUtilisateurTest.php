<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Module 8 — Administration : gestion des comptes (CDC §3.7). */
class AdminUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $awa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['nom' => 'Admin', 'prenom' => 'Super', 'email' => 'admin@example.com']);
        $this->awa = User::factory()->create(['nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'awa@example.com']);
    }

    /** Crée des catégories et des transactions pour un utilisateur ; renvoie [catégorie dépense, catégorie revenu]. */
    private function avecTransactions(User $user, int $depenses = 2, int $revenus = 1): array
    {
        $cd = Categorie::factory()->for($user)->create(['nom' => 'Alimentation']);
        $cr = Categorie::factory()->for($user)->revenu()->create(['nom' => 'Salaire']);
        Depense::factory()->count($depenses)->create(['user_id' => $user->id, 'categorie_id' => $cd->id, 'description' => 'DEPENSE-PRIVEE', 'montant' => 123456]);
        Revenu::factory()->count($revenus)->create(['user_id' => $user->id, 'categorie_id' => $cr->id, 'description' => 'REVENU-PRIVE', 'montant' => 654321]);

        return [$cd, $cr];
    }

    private function enAdmin(): static
    {
        return $this->actingAs($this->admin);
    }

    // ---------- Accès (T8) ----------

    /** T8 */
    public function test_t8_un_utilisateur_simple_recoit_403_sur_toutes_les_routes_admin(): void
    {
        $cible = User::factory()->create(['nom' => 'Cible']);
        $this->actingAs($this->awa);

        $this->get('/admin/utilisateurs')->assertForbidden();
        $this->get("/admin/utilisateurs/{$cible->id}")->assertForbidden();
        $this->get("/admin/utilisateurs/{$cible->id}/edit")->assertForbidden();
        $this->put("/admin/utilisateurs/{$cible->id}", ['nom' => 'Pirate', 'prenom' => 'X', 'email' => 'p@example.com'])->assertForbidden();
        $this->patch("/admin/utilisateurs/{$cible->id}/suspendre")->assertForbidden();
        $this->patch("/admin/utilisateurs/{$cible->id}/reactiver")->assertForbidden();
        $this->delete("/admin/utilisateurs/{$cible->id}")->assertForbidden();

        $cible->refresh();
        $this->assertSame('Cible', $cible->nom);
        $this->assertSame('actif', $cible->statut);
    }

    public function test_un_utilisateur_simple_ne_peut_pas_deviner_quels_comptes_existent(): void
    {
        $existant = User::factory()->create();
        $this->actingAs($this->awa);

        $this->assertSame(403, $this->get("/admin/utilisateurs/{$existant->id}")->status());
        $this->assertSame(403, $this->get('/admin/utilisateurs/999999')->status(), 'même réponse pour un id inexistant');
        $this->assertSame(403, $this->delete('/admin/utilisateurs/999999')->status());
    }

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get('/admin/utilisateurs')->assertRedirect(route('login'));
        $this->get("/admin/utilisateurs/{$this->awa->id}")->assertRedirect(route('login'));
        $this->delete("/admin/utilisateurs/{$this->awa->id}")->assertRedirect(route('login'));
        $this->assertModelExists($this->awa);
    }

    public function test_un_administrateur_suspendu_est_deconnecte(): void
    {
        $this->admin->statut = User::STATUT_SUSPENDU;
        $this->admin->save();

        $this->actingAs($this->admin)->get('/admin/utilisateurs')->assertRedirect(route('login'));
    }

    public function test_l_administrateur_accede_a_l_administration(): void
    {
        $this->enAdmin()->get('/admin/utilisateurs')->assertOk();
        $this->enAdmin()->get("/admin/utilisateurs/{$this->awa->id}")->assertOk();
        $this->enAdmin()->get("/admin/utilisateurs/{$this->awa->id}/edit")->assertOk();
    }

    public function test_un_compte_inexistant_donne_404_a_l_administrateur(): void
    {
        $this->enAdmin()->get('/admin/utilisateurs/999999')->assertNotFound();
        $this->enAdmin()->delete('/admin/utilisateurs/999999')->assertNotFound();
    }

    // ---------- Liste ----------

    public function test_la_liste_affiche_les_comptes_avec_role_et_statut(): void
    {
        User::factory()->suspendu()->create(['nom' => 'Sall', 'prenom' => 'Omar']);

        $this->enAdmin()->get('/admin/utilisateurs')
            ->assertOk()
            ->assertSee('Awa Diop')->assertSee('awa@example.com')
            ->assertSee('Omar Sall')
            ->assertSee('Administrateur')->assertSee('Utilisateur')
            ->assertSee('Actif')->assertSee('Suspendu')
            ->assertSee(format_date($this->awa->created_at));
    }

    public function test_la_liste_est_triee_par_nom(): void
    {
        User::factory()->create(['nom' => 'Zongo', 'prenom' => 'Zoé']);
        User::factory()->create(['nom' => 'Ba', 'prenom' => 'Amadou']);

        $this->enAdmin()->get('/admin/utilisateurs')->assertSeeInOrder(['Super Admin', 'Amadou Ba', 'Awa Diop', 'Zoé Zongo']);
    }

    public function test_la_liste_est_paginee_par_10(): void
    {
        User::factory()->count(23)->create(); // + admin + awa = 25 comptes

        $page1 = $this->enAdmin()->get('/admin/utilisateurs');
        $this->assertSame(10, substr_count($page1->getContent(), 'title="Consulter"'));
        $page1->assertSee('25 comptes')->assertSee('page-link', false);

        $page3 = $this->enAdmin()->get('/admin/utilisateurs?page=3');
        $this->assertSame(5, substr_count($page3->getContent(), 'title="Consulter"'));
    }

    public function test_recherche_par_nom_prenom_email_et_nom_complet(): void
    {
        User::factory()->create(['nom' => 'Ndiaye', 'prenom' => 'Moussa', 'email' => 'moussa@example.com']);

        $this->enAdmin()->get('/admin/utilisateurs?q=ndiaye')->assertSee('Moussa Ndiaye')->assertDontSee('Awa Diop');
        $this->enAdmin()->get('/admin/utilisateurs?q=mouss')->assertSee('Moussa Ndiaye')->assertDontSee('Awa Diop');
        $this->enAdmin()->get('/admin/utilisateurs?q=awa@exam')->assertSee('Awa Diop')->assertDontSee('Moussa Ndiaye');
        $this->enAdmin()->get('/admin/utilisateurs?q='.urlencode('Moussa Ndiaye'))->assertSee('Moussa Ndiaye')->assertDontSee('Awa Diop');
        $this->enAdmin()->get('/admin/utilisateurs?q='.urlencode('Awa Diop'))->assertSee('Awa Diop')->assertDontSee('Moussa Ndiaye');
    }

    public function test_recherche_sans_resultat(): void
    {
        $this->enAdmin()->get('/admin/utilisateurs?q=introuvable')->assertOk()->assertSee('Aucun utilisateur trouvé.');
    }

    public function test_la_recherche_traite_pourcent_et_underscore_comme_du_texte(): void
    {
        User::factory()->create(['nom' => 'A_B', 'prenom' => 'Test', 'email' => 'ab1@example.com']);
        User::factory()->create(['nom' => 'AxB', 'prenom' => 'Test', 'email' => 'ab2@example.com']);

        $this->enAdmin()->get('/admin/utilisateurs?q='.urlencode('A_B'))->assertSee('Test A_B')->assertDontSee('Test AxB');
        $this->enAdmin()->get('/admin/utilisateurs?q='.urlencode('%'))->assertOk()->assertSee('Aucun utilisateur trouvé.');
    }

    public function test_la_recherche_est_conservee_dans_la_pagination(): void
    {
        User::factory()->count(12)->create(['prenom' => 'Zaza']);

        $this->enAdmin()->get('/admin/utilisateurs?q=Zaza')->assertSee('q=Zaza', false)->assertSee('page=2', false);
    }

    public function test_une_injection_dans_la_recherche_est_inoffensive(): void
    {
        $this->enAdmin()->get('/admin/utilisateurs?q='.urlencode("'; DROP TABLE users; --"))->assertOk();
        $this->assertSame(2, User::count());
    }

    public function test_les_boutons_d_action_sont_absents_pour_son_propre_compte(): void
    {
        $html = $this->enAdmin()->get('/admin/utilisateurs')->getContent();

        // Pour un autre compte : suspendre et supprimer sont proposés
        $this->assertStringContainsString(route('admin.utilisateurs.suspendre', $this->awa), $html);
        $this->assertStringContainsString('aria-label="Supprimer Awa Diop"', $html);

        // Pour son propre compte : ni l'un ni l'autre (l'URL de suppression est celle de la fiche, on teste donc le bouton)
        $this->assertStringNotContainsString(route('admin.utilisateurs.suspendre', $this->admin), $html);
        $this->assertStringNotContainsString('aria-label="Supprimer Super Admin"', $html);
        $this->assertStringNotContainsString('aria-label="Suspendre Super Admin"', $html);
        $this->assertStringContainsString('Vous', $html);
    }

    public function test_un_compte_suspendu_propose_reactiver_et_un_compte_actif_propose_suspendre(): void
    {
        $suspendu = User::factory()->suspendu()->create();

        $html = $this->enAdmin()->get('/admin/utilisateurs')->getContent();

        $this->assertStringContainsString(route('admin.utilisateurs.reactiver', $suspendu), $html);
        $this->assertStringNotContainsString(route('admin.utilisateurs.suspendre', $suspendu), $html);
        $this->assertStringContainsString(route('admin.utilisateurs.suspendre', $this->awa), $html);
        $this->assertStringNotContainsString(route('admin.utilisateurs.reactiver', $this->awa), $html);
    }

    public function test_la_suppression_demande_une_confirmation_explicite(): void
    {
        $this->enAdmin()->get('/admin/utilisateurs')->assertSee(
            'data-confirm="Supprimer définitivement le compte de Awa Diop ? Toutes ses transactions et ses catégories seront supprimées."',
            false
        );
    }

    // ---------- Consultation ----------

    public function test_la_fiche_affiche_les_informations_et_le_nombre_de_transactions(): void
    {
        $this->avecTransactions($this->awa, depenses: 3, revenus: 2);

        $this->enAdmin()->get("/admin/utilisateurs/{$this->awa->id}")
            ->assertOk()
            ->assertSee('Diop')->assertSee('Awa')->assertSee('awa@example.com')
            ->assertSee('Utilisateur')
            ->assertSee('Inscrit le')->assertSee(format_date($this->awa->created_at))
            ->assertSee('Nombre de transactions');

        $html = $this->enAdmin()->get("/admin/utilisateurs/{$this->awa->id}")->getContent();
        $this->assertMatchesRegularExpression('/Nombre de transactions<\/dt><dd[^>]*>5</', $html);
    }

    public function test_la_fiche_d_un_compte_sans_transaction_affiche_zero(): void
    {
        $html = $this->enAdmin()->get("/admin/utilisateurs/{$this->awa->id}")->getContent();

        $this->assertMatchesRegularExpression('/Nombre de transactions<\/dt><dd[^>]*>0</', $html);
    }

    /** Confidentialité (CDC §3.7) : l'administrateur ne voit jamais le détail des transactions. */
    public function test_l_administrateur_ne_voit_jamais_le_detail_des_transactions(): void
    {
        $this->avecTransactions($this->awa);

        foreach (["/admin/utilisateurs/{$this->awa->id}", "/admin/utilisateurs/{$this->awa->id}/edit", '/admin/utilisateurs'] as $url) {
            $html = $this->enAdmin()->get($url)->getContent();
            $this->assertStringNotContainsString('DEPENSE-PRIVEE', $html, $url);
            $this->assertStringNotContainsString('REVENU-PRIVE', $html, $url);
            $this->assertStringNotContainsString('123 456', $html, $url);
            $this->assertStringNotContainsString('654 321', $html, $url);
            $this->assertStringNotContainsString('Alimentation', $html, $url);
        }
    }

    public function test_l_administrateur_n_a_aucune_route_vers_les_transactions_des_autres(): void
    {
        [$cd] = $this->avecTransactions($this->awa);
        $depense = Depense::firstOrFail();

        foreach (['/depenses', '/revenus', '/categories', '/historique', '/dashboard', '/statistiques', "/depenses/{$depense->id}/edit"] as $url) {
            $this->enAdmin()->get($url)->assertForbidden();
        }
        $this->enAdmin()->delete("/depenses/{$depense->id}")->assertForbidden();
        $this->enAdmin()->delete("/categories/{$cd->id}")->assertForbidden();
        $this->assertModelExists($depense);
    }

    // ---------- Modification ----------

    public function test_le_formulaire_de_modification_est_prerempli(): void
    {
        $this->enAdmin()->get("/admin/utilisateurs/{$this->awa->id}/edit")
            ->assertOk()
            ->assertSee('value="Awa"', false)->assertSee('value="Diop"', false)->assertSee('value="awa@example.com"', false);
    }

    public function test_modification_du_nom_prenom_et_email(): void
    {
        $this->enAdmin()->put("/admin/utilisateurs/{$this->awa->id}", ['nom' => 'Ndiaye', 'prenom' => 'Fatou', 'email' => 'fatou@example.com'])
            ->assertRedirect(route('admin.utilisateurs.show', $this->awa))
            ->assertSessionHas('success', 'Utilisateur modifié avec succès.');

        $this->awa->refresh();
        $this->assertSame(['Ndiaye', 'Fatou', 'fatou@example.com'], [$this->awa->nom, $this->awa->prenom, $this->awa->email]);
    }

    public function test_modification_en_gardant_le_meme_email_est_acceptee(): void
    {
        $this->enAdmin()->put("/admin/utilisateurs/{$this->awa->id}", ['nom' => 'Autre', 'prenom' => 'Awa', 'email' => 'awa@example.com'])
            ->assertSessionHasNoErrors();
    }

    public function test_modification_avec_un_email_deja_utilise_est_refusee(): void
    {
        User::factory()->create(['email' => 'pris@example.com']);

        $this->enAdmin()->from("/admin/utilisateurs/{$this->awa->id}/edit")
            ->put("/admin/utilisateurs/{$this->awa->id}", ['nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'pris@example.com'])
            ->assertRedirect("/admin/utilisateurs/{$this->awa->id}/edit")
            ->assertSessionHasErrors(['email' => 'Cet e-mail est déjà utilisé.']);

        $this->assertSame('awa@example.com', $this->awa->fresh()->email);
    }

    public function test_modification_champs_obligatoires_et_formats(): void
    {
        $this->enAdmin()->put("/admin/utilisateurs/{$this->awa->id}", ['nom' => '', 'prenom' => '', 'email' => ''])
            ->assertSessionHasErrors(['nom', 'prenom', 'email']);
        $this->enAdmin()->put("/admin/utilisateurs/{$this->awa->id}", ['nom' => 'D', 'prenom' => 'A', 'email' => 'pas-un-email'])
            ->assertSessionHasErrors('email');
        $this->enAdmin()->put("/admin/utilisateurs/{$this->awa->id}", ['nom' => str_repeat('a', 101), 'prenom' => 'A', 'email' => 'ok@example.com'])
            ->assertSessionHasErrors('nom');

        $this->assertSame('Diop', $this->awa->fresh()->nom);
    }

    public function test_la_modification_ne_change_ni_role_ni_statut_ni_mot_de_passe(): void
    {
        $hash = $this->awa->password;

        $this->enAdmin()->put("/admin/utilisateurs/{$this->awa->id}", [
            'nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'awa@example.com',
            'role' => 'admin', 'statut' => 'suspendu', 'password' => 'piratage123', 'id' => 999,
        ]);

        $this->awa->refresh();
        $this->assertSame('utilisateur', $this->awa->role);
        $this->assertSame('actif', $this->awa->statut);
        $this->assertSame($hash, $this->awa->password);
    }

    // ---------- Suspension / réactivation (T3) ----------

    public function test_suspendre_un_compte(): void
    {
        $this->enAdmin()->from('/admin/utilisateurs')->patch("/admin/utilisateurs/{$this->awa->id}/suspendre")
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHas('success', 'Le compte de Awa Diop est suspendu.');

        $this->assertSame('suspendu', $this->awa->fresh()->statut);
    }

    /** T3 : un compte suspendu ne peut plus se connecter. */
    public function test_t3_un_compte_suspendu_par_l_admin_ne_peut_plus_se_connecter(): void
    {
        $this->enAdmin()->patch("/admin/utilisateurs/{$this->awa->id}/suspendre");
        auth()->logout();

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors(['email' => 'Votre compte est suspendu. Contactez l\'administrateur.']);
        $this->assertGuest();
    }

    public function test_une_session_ouverte_est_coupee_apres_la_suspension(): void
    {
        $this->actingAs($this->awa)->get('/dashboard')->assertOk();

        $this->awa->statut = User::STATUT_SUSPENDU;
        $this->awa->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reactiver_un_compte_lui_rend_l_acces(): void
    {
        $this->awa->statut = User::STATUT_SUSPENDU;
        $this->awa->save();

        $this->enAdmin()->from('/admin/utilisateurs')->patch("/admin/utilisateurs/{$this->awa->id}/reactiver")
            ->assertSessionHas('success', 'Le compte de Awa Diop est réactivé.');
        $this->assertSame('actif', $this->awa->fresh()->statut);

        auth()->logout();
        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->awa);
    }

    public function test_suspendre_ne_supprime_aucune_donnee(): void
    {
        $this->avecTransactions($this->awa);

        $this->enAdmin()->patch("/admin/utilisateurs/{$this->awa->id}/suspendre");

        $this->assertSame(2, Depense::count());
        $this->assertSame(1, Revenu::count());
        $this->assertSame(2, Categorie::count());
    }

    public function test_l_admin_ne_peut_pas_se_suspendre_lui_meme(): void
    {
        $this->enAdmin()->from('/admin/utilisateurs')->patch("/admin/utilisateurs/{$this->admin->id}/suspendre")
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHas('error', 'Vous ne pouvez pas suspendre votre propre compte.');

        $this->assertSame('actif', $this->admin->fresh()->statut);
        $this->enAdmin()->get('/admin/utilisateurs')->assertOk();
    }

    public function test_un_compte_administrateur_ne_peut_etre_ni_suspendu_ni_supprime_par_un_autre(): void
    {
        $autreAdmin = User::factory()->admin()->create(['nom' => 'Autre', 'prenom' => 'Admin']);

        $this->enAdmin()->from('/admin/utilisateurs')->patch("/admin/utilisateurs/{$autreAdmin->id}/suspendre")
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHas('error', 'Un compte administrateur ne peut être ni suspendu ni supprimé.');
        $this->enAdmin()->from('/admin/utilisateurs')->delete("/admin/utilisateurs/{$autreAdmin->id}")
            ->assertSessionHas('error', 'Un compte administrateur ne peut être ni suspendu ni supprimé.');

        $autreAdmin->refresh();
        $this->assertSame('actif', $autreAdmin->statut);
        $this->assertModelExists($autreAdmin);
    }

    public function test_le_message_propre_est_donne_pour_son_compte_et_celui_d_un_autre_administrateur(): void
    {
        $autreAdmin = User::factory()->admin()->create();

        $this->enAdmin()->patch("/admin/utilisateurs/{$this->admin->id}/suspendre")
            ->assertSessionHas('error', 'Vous ne pouvez pas suspendre votre propre compte.');
        $this->enAdmin()->patch("/admin/utilisateurs/{$autreAdmin->id}/suspendre")
            ->assertSessionHas('error', 'Un compte administrateur ne peut être ni suspendu ni supprimé.');
    }

    public function test_les_boutons_suspendre_et_supprimer_sont_absents_pour_tout_compte_administrateur(): void
    {
        $autreAdmin = User::factory()->admin()->create(['nom' => 'Autre', 'prenom' => 'Admin']);

        $liste = $this->enAdmin()->get('/admin/utilisateurs')->getContent();
        $this->assertStringNotContainsString(route('admin.utilisateurs.suspendre', $autreAdmin), $liste);
        $this->assertStringNotContainsString('aria-label="Supprimer Admin Autre"', $liste);
        // Un compte utilisateur, lui, garde ses boutons
        $this->assertStringContainsString(route('admin.utilisateurs.suspendre', $this->awa), $liste);

        $fiche = $this->enAdmin()->get("/admin/utilisateurs/{$autreAdmin->id}");
        // (le mot « Supprimer » figure aussi dans la fenêtre de confirmation commune : on teste les vrais formulaires)
        $fiche->assertOk()
            ->assertDontSee(route('admin.utilisateurs.suspendre', $autreAdmin), false)
            ->assertDontSee('data-confirm="Supprimer définitivement', false)
            ->assertDontSee('btn btn-outline-warning', false)        // bouton « Suspendre »
            ->assertDontSee('<i class="bi bi-trash me-1"></i>', false) // bouton « Supprimer » (celui de la barre du haut est « Déconnexion »)
            ->assertSee('Un compte administrateur ne peut être ni suspendu ni supprimé.')
            ->assertSee(route('admin.utilisateurs.edit', $autreAdmin), false);
    }

    public function test_un_administrateur_reste_modifiable_et_un_compte_admin_suspendu_peut_etre_reactive(): void
    {
        $autreAdmin = User::factory()->admin()->suspendu()->create(['email' => 'autre@example.com']);

        $this->enAdmin()->put("/admin/utilisateurs/{$autreAdmin->id}", ['nom' => 'Nouveau', 'prenom' => 'Nom', 'email' => 'autre@example.com'])
            ->assertSessionHasNoErrors();
        $this->enAdmin()->patch("/admin/utilisateurs/{$autreAdmin->id}/reactiver")->assertSessionHas('success');

        $autreAdmin->refresh();
        $this->assertSame('Nouveau', $autreAdmin->nom);
        $this->assertSame('actif', $autreAdmin->statut);
    }

    // ---------- Suppression ----------

    public function test_suppression_d_un_compte_sans_transaction(): void
    {
        $this->enAdmin()->delete("/admin/utilisateurs/{$this->awa->id}")
            ->assertRedirect(route('admin.utilisateurs.index'))
            ->assertSessionHas('success', 'Le compte de Awa Diop a été supprimé.');

        $this->assertModelMissing($this->awa);
    }

    /** Cas qui échoue avec une simple suppression d'utilisateur (RESTRICT) : traité par la suppression ordonnée. */
    public function test_suppression_d_un_compte_avec_transactions_et_categories_utilisees(): void
    {
        $this->avecTransactions($this->awa, depenses: 3, revenus: 2);
        $this->assertSame(5, Depense::count() + Revenu::count());

        $this->enAdmin()->delete("/admin/utilisateurs/{$this->awa->id}")->assertRedirect(route('admin.utilisateurs.index'));

        $this->assertModelMissing($this->awa);
        $this->assertSame(0, Depense::count());
        $this->assertSame(0, Revenu::count());
        $this->assertSame(0, Categorie::where('user_id', $this->awa->id)->count());
    }

    public function test_la_suppression_n_affecte_pas_les_autres_comptes(): void
    {
        $moussa = User::factory()->create();
        $this->avecTransactions($this->awa);
        [$cd] = $this->avecTransactions($moussa, depenses: 4, revenus: 3);

        $this->enAdmin()->delete("/admin/utilisateurs/{$this->awa->id}");

        $this->assertModelExists($moussa);
        $this->assertModelExists($cd);
        $this->assertSame(4, Depense::where('user_id', $moussa->id)->count());
        $this->assertSame(3, Revenu::where('user_id', $moussa->id)->count());
        $this->assertSame(2, Categorie::where('user_id', $moussa->id)->count());
        $this->assertModelExists($this->admin);
    }

    public function test_la_suppression_est_atomique_en_cas_d_erreur(): void
    {
        $this->avecTransactions($this->awa);

        // Fait échouer la dernière étape (suppression du compte) : tout doit être annulé.
        User::deleting(function (User $u) {
            if ($u->is($this->awa)) {
                throw new \RuntimeException('échec simulé');
            }
        });

        try {
            $this->enAdmin()->withoutExceptionHandling()->delete("/admin/utilisateurs/{$this->awa->id}");
            $this->fail('Une exception était attendue');
        } catch (\RuntimeException $e) {
            $this->assertSame('échec simulé', $e->getMessage());
        } finally {
            User::flushEventListeners();
        }

        $this->assertModelExists($this->awa);
        $this->assertSame(2, Depense::count(), 'les dépenses doivent être restaurées');
        $this->assertSame(1, Revenu::count(), 'les revenus doivent être restaurés');
    }

    public function test_un_compte_supprime_ne_peut_plus_se_connecter(): void
    {
        $this->enAdmin()->delete("/admin/utilisateurs/{$this->awa->id}");
        auth()->logout();

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors(['email' => "Aucun compte n'est associé à cette adresse e-mail."]);
        $this->assertGuest();
    }

    public function test_l_admin_ne_peut_pas_se_supprimer_lui_meme(): void
    {
        $this->enAdmin()->from('/admin/utilisateurs')->delete("/admin/utilisateurs/{$this->admin->id}")
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHas('error', 'Vous ne pouvez pas supprimer votre propre compte.');

        $this->assertModelExists($this->admin);
    }

    public function test_les_comptes_supprimes_laissent_une_base_coherente(): void
    {
        $this->avecTransactions($this->awa);

        $this->enAdmin()->delete("/admin/utilisateurs/{$this->awa->id}");

        // Aucune ligne orpheline : toute transaction / catégorie restante a un propriétaire
        $this->assertSame(0, DB::table('depenses')->whereNotIn('user_id', User::pluck('id'))->count());
        $this->assertSame(0, DB::table('revenus')->whereNotIn('user_id', User::pluck('id'))->count());
        $this->assertSame(0, DB::table('categories')->whereNotIn('user_id', User::pluck('id'))->count());
    }

    // ---------- Navigation ----------

    public function test_le_menu_de_l_administrateur_ne_propose_que_utilisateurs_et_profil(): void
    {
        $html = $this->enAdmin()->get('/admin/utilisateurs')->getContent();

        $this->assertMatchesRegularExpression('/class="nav-link active"\s+href="[^"]*\/admin\/utilisateurs"/', $html);
        $this->assertStringContainsString(route('profil.edit'), $html);
        foreach (['depenses.index', 'revenus.index', 'categories.index', 'statistiques', 'historique', 'dashboard'] as $route) {
            $this->assertStringNotContainsString('href="'.route($route).'"', $html, $route);
        }
    }

    public function test_le_logo_mene_a_l_espace_de_chaque_role_et_jamais_a_une_page_interdite(): void
    {
        $this->enAdmin()->get('/profil')->assertSee('href="'.route('admin.dashboard').'" class="sb-brand"', false);

        $this->actingAs($this->awa)->get('/profil')->assertSee('href="'.route('dashboard').'" class="sb-brand"', false);
    }

    public function test_les_comptes_de_demonstration_se_gerent_depuis_l_administration(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@smartbudget.sn')->firstOrFail();

        $reponse = $this->actingAs($admin)->get('/admin/utilisateurs');

        $reponse->assertOk()->assertSee('awa@smartbudget.sn')->assertSee('moussa@smartbudget.sn');
        $this->assertTrue(Hash::check('Admin1234', $admin->password));
    }
}
