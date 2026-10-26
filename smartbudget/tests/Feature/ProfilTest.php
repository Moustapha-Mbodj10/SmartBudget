<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Module 5 — Gestion du profil (EF3). */
class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'awa@example.com', 'password' => 'ancienmdp1',
        ]);
    }

    private function infos(array $surcharge = []): array
    {
        return array_merge(['nom' => 'Ndiaye', 'prenom' => 'Fatou', 'email' => 'fatou@example.com'], $surcharge);
    }

    private function mdp(array $surcharge = []): array
    {
        return array_merge([
            'current_password' => 'ancienmdp1', 'password' => 'nouveaumdp2', 'password_confirmation' => 'nouveaumdp2',
        ], $surcharge);
    }

    // ---------- Accès ----------

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get('/profil')->assertRedirect(route('login'));
        $this->put('/profil', $this->infos())->assertRedirect(route('login'));
        $this->put('/profil/password', $this->mdp())->assertRedirect(route('login'));
    }

    public function test_un_compte_suspendu_est_deconnecte(): void
    {
        $this->actingAs(User::factory()->suspendu()->create())->get('/profil')->assertRedirect(route('login'));
    }

    public function test_le_profil_est_accessible_a_l_utilisateur_et_a_l_administrateur(): void
    {
        $this->actingAs($this->user)->get('/profil')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/profil')->assertOk();
    }

    // ---------- Consultation ----------

    public function test_la_page_affiche_les_informations_de_l_utilisateur(): void
    {
        $this->actingAs($this->user)->get('/profil')
            ->assertOk()
            ->assertSee('value="Awa"', false)
            ->assertSee('value="Diop"', false)
            ->assertSee('value="awa@example.com"', false)
            ->assertSee('Utilisateur')
            ->assertSee('Inscrit le '.$this->user->created_at->format('d/m/Y'));
    }

    public function test_la_page_indique_le_role_administrateur(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/profil')->assertSee('Administrateur');
    }

    public function test_la_page_a_deux_onglets_et_n_expose_jamais_le_mot_de_passe(): void
    {
        $reponse = $this->actingAs($this->user)->get('/profil');

        $reponse->assertSee('Informations')->assertSee('Mot de passe');
        $reponse->assertDontSee($this->user->password, false);
    }

    public function test_l_onglet_informations_est_actif_par_defaut(): void
    {
        $html = $this->actingAs($this->user)->get('/profil')->getContent();

        $this->assertMatchesRegularExpression('/id="onglet-infos-tab" class="nav-link active"/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="onglet-mdp-tab" class="nav-link active"/', $html);
    }

    // ---------- Modification des informations ----------

    public function test_modification_du_nom_et_du_prenom(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->infos())
            ->assertRedirect(route('profil.edit'))
            ->assertSessionHas('success', 'Vos informations ont été mises à jour.');

        $this->user->refresh();
        $this->assertSame('Ndiaye', $this->user->nom);
        $this->assertSame('Fatou', $this->user->prenom);
        $this->assertSame('awa@example.com', $this->user->email, "l'e-mail envoyé avec le formulaire n'est pas pris en compte");
    }

    public function test_le_nouveau_nom_apparait_dans_la_barre_du_haut(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->infos());

        $this->actingAs($this->user->fresh())->get('/profil')->assertSee('Fatou Ndiaye');
    }

    // ---------- L'adresse e-mail est définitive dès l'inscription ----------

    public function test_l_email_ne_peut_pas_etre_modifie_meme_en_forcant_la_requete(): void
    {
        foreach (['fatou@example.com', 'pas-un-email', '', str_repeat('a', 200).'@x.sn'] as $essai) {
            $this->actingAs($this->user)->put('/profil', $this->infos(['email' => $essai]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('profil.edit'));

            $this->assertSame('awa@example.com', $this->user->fresh()->email, "tentative : $essai");
        }
        $this->assertSame('Fatou', $this->user->fresh()->prenom, 'nom et prénom sont bien enregistrés');
    }

    public function test_l_email_d_un_autre_utilisateur_n_est_pas_non_plus_modifiable_par_le_profil(): void
    {
        $autre = User::factory()->create(['email' => 'pris@example.com']);

        $this->actingAs($this->user)->put('/profil', $this->infos(['email' => 'pris@example.com', 'id' => $autre->id]))->assertSessionHasNoErrors();

        $this->assertSame('awa@example.com', $this->user->fresh()->email);
        $this->assertSame('pris@example.com', $autre->fresh()->email);
    }

    public function test_la_page_profil_affiche_l_email_en_lecture_seule_sans_le_renvoyer(): void
    {
        $html = $this->actingAs($this->user)->get('/profil')->getContent();

        $this->assertMatchesRegularExpression('~<input type="email" id="email" value="awa@example.com"[^>]*readonly~', $html);
        $this->assertDoesNotMatchRegularExpression('~<input[^>]*type="email"[^>]*name="email"~', $html, "pas de champ « email » dans le formulaire");
        $this->assertDoesNotMatchRegularExpression('~<input[^>]*name="email"[^>]*type="email"~', $html);
        $this->assertStringContainsString("ne peut pas être modifiée après l'inscription", $html);
    }

    public function test_l_inscription_demande_toujours_l_email_et_le_mot_de_passe_garde_son_adresse(): void
    {
        $this->post('/logout');
        $this->get('/register')->assertSee('name="email"', false);

        $this->post('/register', [
            'nom' => 'Sy', 'prenom' => 'Ali', 'email' => 'ali@example.com',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'ali@example.com')->first());
    }

    public function test_champs_obligatoires(): void
    {
        $this->actingAs($this->user)->put('/profil', ['nom' => '', 'prenom' => ''])
            ->assertSessionHasErrors(['nom', 'prenom']);

        $this->assertSame('Awa', $this->user->fresh()->prenom);
    }

    public function test_longueurs_maximales(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->infos(['nom' => str_repeat('a', 101)]))->assertSessionHasErrors('nom');
        $this->actingAs($this->user)->put('/profil', $this->infos(['prenom' => str_repeat('a', 101)]))->assertSessionHasErrors('prenom');
        $this->actingAs($this->user)->put('/profil', $this->infos(['nom' => str_repeat('a', 100)]))->assertSessionHasNoErrors();
    }

    public function test_une_erreur_conserve_les_valeurs_saisies(): void
    {
        $this->actingAs($this->user)->from('/profil')->put('/profil', $this->infos(['nom' => str_repeat('a', 101), 'prenom' => 'GardeMoi']));

        $this->actingAs($this->user)->get('/profil')->assertSee('value="GardeMoi"', false);
    }

    public function test_la_modification_ne_peut_pas_changer_role_statut_ni_mot_de_passe(): void
    {
        $ancienHash = $this->user->password;

        $this->actingAs($this->user)->put('/profil', $this->infos([
            'role' => 'admin', 'statut' => 'suspendu', 'password' => 'piratage123', 'id' => 999,
        ]));

        $this->user->refresh();
        $this->assertSame('utilisateur', $this->user->role);
        $this->assertSame('actif', $this->user->statut);
        $this->assertSame($ancienHash, $this->user->password);
        $this->assertSame($this->user->id, User::where('email', 'awa@example.com')->firstOrFail()->id);
    }

    public function test_on_ne_modifie_que_son_propre_profil(): void
    {
        $autre = User::factory()->create(['nom' => 'Autre', 'email' => 'autre@example.com']);

        $this->actingAs($this->user)->put('/profil', $this->infos(['user_id' => $autre->id, 'id' => $autre->id]));

        $this->assertSame('Autre', $autre->fresh()->nom);
        $this->assertSame('autre@example.com', $autre->fresh()->email);
        $this->assertSame('Ndiaye', $this->user->fresh()->nom);
    }

    public function test_l_administrateur_peut_modifier_son_profil(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/profil', $this->infos())->assertSessionHasNoErrors();

        $this->assertSame('Fatou', $admin->fresh()->prenom);
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    // ---------- Changement de mot de passe ----------

    public function test_changement_de_mot_de_passe(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp())
            ->assertRedirect(route('profil.edit'))
            ->assertSessionHas('success', 'Votre mot de passe a été modifié.')
            ->assertSessionHas('onglet', 'password');

        $hash = $this->user->fresh()->password;
        $this->assertTrue(Hash::check('nouveaumdp2', $hash));
        $this->assertFalse(Hash::check('ancienmdp1', $hash));
        $this->assertStringStartsWith('$2y$', $hash, 'Le mot de passe doit être haché en bcrypt');
    }

    public function test_apres_le_changement_seul_le_nouveau_mot_de_passe_permet_de_se_connecter(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp());
        $this->post('/logout');

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'ancienmdp1'])->assertSessionHasErrors('password');
        $this->assertGuest();

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'nouveaumdp2'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_apres_le_changement_l_utilisateur_reste_connecte_sur_l_onglet_mot_de_passe(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp());

        $reponse = $this->actingAs($this->user->fresh())->withSession(['onglet' => 'password'])->get('/profil');

        $reponse->assertOk();
        $this->assertMatchesRegularExpression('/id="onglet-mdp-tab" class="nav-link active"/', $reponse->getContent());
    }

    public function test_ancien_mot_de_passe_incorrect_refuse(): void
    {
        $hash = $this->user->password;

        $this->actingAs($this->user)->from('/profil')->put('/profil/password', $this->mdp(['current_password' => 'faux']))
            ->assertRedirect('/profil')
            ->assertSessionHasErrors(['current_password' => 'Le mot de passe est incorrect.'], null, 'password');

        $this->assertSame($hash, $this->user->fresh()->password);
    }

    public function test_ancien_mot_de_passe_obligatoire(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp(['current_password' => '']))
            ->assertSessionHasErrors('current_password', null, 'password');
    }

    public function test_nouveau_mot_de_passe_obligatoire(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('password', null, 'password');
    }

    public function test_nouveau_mot_de_passe_trop_court_refuse(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp(['password' => '1234567', 'password_confirmation' => '1234567']))
            ->assertSessionHasErrors('password', null, 'password');

        $this->assertTrue(Hash::check('ancienmdp1', $this->user->fresh()->password));
    }

    public function test_nouveau_mot_de_passe_de_8_caracteres_accepte(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp(['password' => '12345678', 'password_confirmation' => '12345678']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('12345678', $this->user->fresh()->password));
    }

    public function test_confirmation_differente_refusee(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp(['password_confirmation' => 'autrechose']))
            ->assertSessionHasErrors('password', null, 'password');

        $this->assertTrue(Hash::check('ancienmdp1', $this->user->fresh()->password));
    }

    public function test_les_erreurs_du_mot_de_passe_ouvrent_l_onglet_mot_de_passe(): void
    {
        $this->actingAs($this->user)->from('/profil')->put('/profil/password', $this->mdp(['current_password' => 'faux']));

        $reponse = $this->actingAs($this->user)->get('/profil');

        $this->assertMatchesRegularExpression('/id="onglet-mdp-tab" class="nav-link active"/', $reponse->getContent());
        $reponse->assertSee('Le mot de passe est incorrect.');
    }

    public function test_les_erreurs_des_deux_formulaires_sont_separees(): void
    {
        // Une erreur sur les informations n'ouvre pas l'onglet mot de passe
        $this->actingAs($this->user)->from('/profil')->put('/profil', $this->infos(['nom' => str_repeat('a', 101)]));

        $html = $this->actingAs($this->user)->get('/profil')->getContent();

        $this->assertMatchesRegularExpression('/id="onglet-infos-tab" class="nav-link active"/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="onglet-mdp-tab" class="nav-link active"/', $html);
    }

    public function test_changement_de_mot_de_passe_ne_modifie_pas_les_informations(): void
    {
        $this->actingAs($this->user)->put('/profil/password', $this->mdp(['nom' => 'Pirate', 'email' => 'pirate@example.com']));

        $this->user->refresh();
        $this->assertSame('Diop', $this->user->nom);
        $this->assertSame('awa@example.com', $this->user->email);
    }

    public function test_changement_de_mot_de_passe_limite_a_5_essais_par_minute(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->user)->put('/profil/password', $this->mdp(['current_password' => 'faux']))->assertStatus(302);
        }

        $this->actingAs($this->user)->put('/profil/password', $this->mdp())->assertStatus(429);
        $this->assertTrue(Hash::check('ancienmdp1', $this->user->fresh()->password), 'Le 6e essai ne doit pas passer');
    }

    public function test_l_administrateur_peut_changer_son_mot_de_passe(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'adminmdp1']);

        $this->actingAs($admin)->put('/profil/password', [
            'current_password' => 'adminmdp1', 'password' => 'adminmdp2x', 'password_confirmation' => 'adminmdp2x',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('adminmdp2x', $admin->fresh()->password));
    }

    // ---------- Navigation ----------

    public function test_le_menu_met_mon_profil_en_surbrillance(): void
    {
        $html = $this->actingAs($this->user)->get('/profil')->getContent();

        $this->assertMatchesRegularExpression('/class="nav-link active"\s+href="[^"]*\/profil"/', $html);
    }

    public function test_le_menu_de_l_administrateur_propose_le_profil(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/profil')
            ->assertSee(route('profil.edit'), false)
            ->assertDontSee(route('depenses.index'), false);
    }
}
