<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Module 1 — Authentification (EF1 inscription, EF2 connexion, EF12 déconnexion). */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function donneesInscription(array $surcharge = []): array
    {
        return array_merge([
            'nom' => 'Diop',
            'prenom' => 'Awa',
            'email' => 'awa@example.com',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
        ], $surcharge);
    }

    // ---------- Pages ----------

    public function test_les_pages_connexion_et_inscription_s_affichent(): void
    {
        $this->get('/login')->assertOk()->assertSee('Se connecter');
        $this->get('/register')->assertOk()->assertSee("S'inscrire", false);
    }

    public function test_la_racine_redirige_selon_l_etat_de_connexion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    // ---------- EF1 : inscription ----------

    public function test_inscription_reussie_cree_un_utilisateur_actif_avec_mot_de_passe_hache(): void
    {
        $response = $this->post('/register', $this->donneesInscription());

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'awa@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('utilisateur', $user->role);
        $this->assertSame('actif', $user->statut);
        $this->assertNotSame('motdepasse1', $user->password);
        $this->assertTrue(Hash::check('motdepasse1', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password, 'Le mot de passe doit être haché en bcrypt');
    }

    /** T1 */
    public function test_t1_inscription_avec_un_email_deja_utilise_est_refusee(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $response = $this->from('/register')->post('/register', $this->donneesInscription());

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['email' => 'Cet e-mail est déjà utilisé.']);
        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_inscription_champs_obligatoires(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['nom', 'prenom', 'email', 'password']);
        $this->assertSame(0, User::count());
    }

    public function test_inscription_email_invalide(): void
    {
        $this->post('/register', $this->donneesInscription(['email' => 'pas-un-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_inscription_mot_de_passe_trop_court(): void
    {
        $this->post('/register', $this->donneesInscription(['password' => '1234567', 'password_confirmation' => '1234567']))
            ->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
    }

    public function test_inscription_mot_de_passe_de_8_caracteres_accepte(): void
    {
        $this->post('/register', $this->donneesInscription(['password' => '12345678', 'password_confirmation' => '12345678']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, User::count());
    }

    public function test_inscription_confirmation_differente(): void
    {
        $this->post('/register', $this->donneesInscription(['password_confirmation' => 'autre-chose']))
            ->assertSessionHasErrors('password');
    }

    public function test_inscription_nom_trop_long(): void
    {
        $this->post('/register', $this->donneesInscription(['nom' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('nom');
    }

    public function test_inscription_ne_permet_pas_de_se_donner_le_role_admin(): void
    {
        $this->post('/register', $this->donneesInscription(['role' => 'admin', 'statut' => 'suspendu']));

        $user = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertSame('utilisateur', $user->role);
        $this->assertSame('actif', $user->statut);
    }

    // ---------- EF2 : connexion ----------

    public function test_connexion_utilisateur_redirige_vers_le_tableau_de_bord(): void
    {
        $user = User::factory()->create(['email' => 'awa@example.com']);

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_connexion_administrateur_redirige_vers_l_administration(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    /** T2 */
    public function test_t2_connexion_avec_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $response = $this->from('/login')->post('/login', ['email' => 'awa@example.com', 'password' => 'mauvais']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['password' => 'Mot de passe incorrect.']);
        $response->assertSessionDoesntHaveErrors('email');
        $this->assertGuest();
    }

    public function test_messages_distincts_pour_email_inconnu_et_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $this->post('/login', ['email' => 'inconnu@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors(['email' => "Aucun compte n'est associé à cette adresse e-mail."]);

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['password' => 'Mot de passe incorrect.']);
    }

    public function test_le_message_de_mot_de_passe_incorrect_s_affiche_sous_le_champ_mot_de_passe_et_garde_l_email(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $this->from('/login')->post('/login', ['email' => 'awa@example.com', 'password' => 'mauvais']);
        $html = $this->get('/login')->getContent();

        $this->assertMatchesRegularExpression('~id="password".*?Mot de passe incorrect.~s', $html);
        $this->assertStringContainsString('value="awa@example.com"', $html);
        $this->assertStringNotContainsString('mauvais', $html);
    }

    public function test_un_mauvais_mot_de_passe_sur_un_compte_suspendu_ne_dit_pas_que_le_compte_est_suspendu(): void
    {
        User::factory()->suspendu()->create(['email' => 'awa@example.com']);

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['password' => 'Mot de passe incorrect.']);
    }

    /** T3 */
    public function test_t3_connexion_d_un_compte_suspendu_est_refusee(): void
    {
        User::factory()->suspendu()->create(['email' => 'awa@example.com']);

        $response = $this->from('/login')->post('/login', ['email' => 'awa@example.com', 'password' => 'password123']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Votre compte est suspendu. Contactez l\'administrateur.']);
        $this->assertGuest();
    }

    public function test_connexion_champs_obligatoires(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_connexion_regenere_la_session(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);
        $this->get('/login');
        $avant = session()->getId();

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123']);

        $this->assertNotSame($avant, session()->getId());
    }

    public function test_limitation_a_5_tentatives_de_connexion_par_minute(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => 'awa@example.com', 'password' => 'mauvais'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123'])->assertStatus(429);
        $this->assertGuest();
    }

    // ---------- EF12 : déconnexion ----------

    public function test_deconnexion_invalide_la_session_et_redirige_vers_la_connexion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_la_deconnexion_exige_d_etre_connecte(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    // ---------- Middlewares auth / guest / actif / admin ----------

    public function test_pages_protegees_redirigent_les_invites_vers_la_connexion(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/utilisateurs')->assertRedirect(route('login'));
    }

    public function test_un_utilisateur_connecte_est_redirige_hors_des_pages_invite(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/register')->assertRedirect(route('dashboard'));
    }

    public function test_un_admin_connecte_est_redirige_vers_l_administration(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_middleware_actif_deconnecte_un_utilisateur_suspendu_en_cours_de_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->statut = User::STATUT_SUSPENDU;
        $user->save();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** T8 (partie middleware) */
    public function test_middleware_admin_refuse_un_utilisateur_simple_avec_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/utilisateurs')->assertForbidden();
    }

    public function test_middleware_admin_autorise_l_administrateur(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/utilisateurs')->assertOk();
    }

    public function test_la_page_403_est_en_francais(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/utilisateurs')
            ->assertForbidden()
            ->assertSee('Accès refusé');
    }

    // ---------- Base de données / modèle ----------

    public function test_valeurs_par_defaut_en_base_role_utilisateur_statut_actif(): void
    {
        \Illuminate\Support\Facades\DB::table('users')->insert([
            'nom' => 'A', 'prenom' => 'B', 'email' => 'x@example.com', 'password' => 'x',
        ]);

        $user = User::where('email', 'x@example.com')->firstOrFail();
        $this->assertSame('utilisateur', $user->role);
        $this->assertSame('actif', $user->statut);
    }

    public function test_l_email_est_unique_en_base(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        User::factory()->create(['email' => 'dup@example.com']);
    }

    public function test_methodes_du_modele_user(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->make()->isAdmin());
        $this->assertTrue(User::factory()->make()->isActif());
        $this->assertFalse(User::factory()->suspendu()->make()->isActif());
    }

    public function test_les_comptes_de_demonstration_sont_crees_par_le_seeder(): void
    {
        $this->seed();

        $this->assertSame(3, User::count());
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(2, User::where('role', 'utilisateur')->count());

        $this->post('/login', ['email' => 'admin@smartbudget.sn', 'password' => 'Admin1234'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_le_seeder_est_rejouable_sans_doublon(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(3, User::count());
    }
}
