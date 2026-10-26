<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tableau de bord de l'administrateur : comptes, statut actif / suspendu, en ligne / déconnecté et durée de déconnexion. */
class AdminTableauDeBordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
    }

    private function utilisateur(string $prenom, array $suivi = [], bool $suspendu = false): User
    {
        $u = User::factory()->create(['prenom' => $prenom, 'nom' => 'Test', 'email' => strtolower($prenom).'@example.com']);
        if ($suspendu) {
            $u->statut = User::STATUT_SUSPENDU;
            $u->save();
        }
        $u->forceFill($suivi)->save();

        return $u->fresh();
    }

    // ---------- Accès ----------

    public function test_l_administrateur_arrive_sur_son_tableau_de_bord_apres_la_connexion(): void
    {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password123'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('/admin', route('admin.dashboard', [], false));
    }

    public function test_le_tableau_de_bord_est_reserve_a_l_administrateur(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Statut des utilisateurs');
    }

    public function test_l_administrateur_n_a_pas_acces_au_tableau_de_bord_budget_mais_a_le_sien_dans_le_menu(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin')->getContent();

        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertStringContainsString('href="'.route('admin.utilisateurs.index').'"', $html);
        $this->actingAs($this->admin)->get('/dashboard')->assertForbidden();
    }

    // ---------- Compteurs ----------

    public function test_les_compteurs_comptent_les_comptes_utilisateurs_seulement(): void
    {
        $this->utilisateur('Enligne', ['derniere_activite' => now()->subMinute()]);
        $this->utilisateur('Absent', ['derniere_activite' => now()->subHours(3)]);
        $this->utilisateur('Suspendu', ['derniere_activite' => now()->subDays(2)], suspendu: true);

        $reponse = $this->actingAs($this->admin)->get('/admin')->assertOk();

        $this->assertSame(3, $reponse->viewData('total'), "l'administrateur n'est pas compté");
        $this->assertSame(1, $reponse->viewData('enLigne'));
        $this->assertSame(2, $reponse->viewData('deconnectes'));
        $this->assertSame(2, $reponse->viewData('actifs'));
        $this->assertSame(1, $reponse->viewData('suspendus'));
        $this->assertSame(3, $reponse->viewData('nouveauxCeMois'));
    }

    public function test_tableau_de_bord_sans_aucun_utilisateur(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Aucun compte utilisateur');
    }

    // ---------- En ligne / déconnecté ----------

    public function test_une_personne_active_depuis_moins_de_5_minutes_est_en_ligne(): void
    {
        $this->assertTrue($this->utilisateur('Un', ['derniere_activite' => now()->subMinutes(4)])->estEnLigne());
        $this->assertFalse($this->utilisateur('Deux', ['derniere_activite' => now()->subMinutes(6)])->estEnLigne());
        $this->assertFalse($this->utilisateur('Trois')->estEnLigne(), 'jamais connectée');
    }

    public function test_une_deconnexion_volontaire_met_hors_ligne_tout_de_suite(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subSeconds(10)]);
        $this->assertTrue($u->estEnLigne());

        $this->actingAs($u)->post('/logout');

        $u = $u->fresh();
        $this->assertFalse($u->estEnLigne());
        $this->assertNotNull($u->deconnecte_le);
    }

    public function test_la_duree_de_deconnexion_est_ecrite_en_toutes_lettres(): void
    {
        $this->assertSame('2 heures et 15 minutes', $this->utilisateur('Ndeye', ['derniere_activite' => now()->subMinutes(135)])->dureeDeconnexion());
        $this->assertSame('3 jours et 4 heures', $this->utilisateur('Fatou', ['derniere_activite' => now()->subHours(76)])->dureeDeconnexion());
        $this->assertNull($this->utilisateur('Enligne', ['derniere_activite' => now()])->dureeDeconnexion());
        $this->assertNull($this->utilisateur('Jamais')->dureeDeconnexion());
    }

    public function test_la_duree_n_affiche_jamais_de_secondes_au_dela_d_une_minute(): void
    {
        $this->assertSame('6 mois', $this->utilisateur('Un', ['derniere_activite' => now()->subMonths(6)->subSeconds(43)])->dureeDeconnexion());
        $this->assertSame('2 heures et 15 minutes', $this->utilisateur('Deux', ['derniere_activite' => now()->subMinutes(135)->subSeconds(40)])->dureeDeconnexion());
        $this->assertSame('3 jours', $this->utilisateur('Trois', ['derniere_activite' => now()->subDays(3)->subSeconds(50)])->dureeDeconnexion());
    }

    public function test_la_duree_part_de_la_deconnexion_volontaire_quand_elle_existe(): void
    {
        $u = $this->utilisateur('Moussa', ['derniere_activite' => now()->subHours(10), 'deconnecte_le' => now()->subMinutes(30)]);

        $this->assertSame('30 minutes', $u->dureeDeconnexion());
    }

    public function test_le_tableau_affiche_en_ligne_deconnecte_et_la_duree(): void
    {
        $this->utilisateur('Enligne', ['derniere_activite' => now()->subMinute()]);
        $this->utilisateur('Absente', ['derniere_activite' => now()->subMinutes(135)]);
        $this->utilisateur('Nouveau');

        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSee('En ligne')
            ->assertSee('Déconnecté')
            ->assertSee('depuis 2 heures et 15 minutes')
            ->assertSee('aucune connexion enregistrée');
    }

    public function test_les_personnes_en_ligne_sont_listees_en_premier_puis_les_deconnexions_recentes(): void
    {
        $this->utilisateur('Ancienne', ['derniere_activite' => now()->subDays(5)]);
        $this->utilisateur('Jamais');
        $this->utilisateur('Recente', ['derniere_activite' => now()->subHour()]);
        $this->utilisateur('Enligne', ['derniere_activite' => now()->subMinute()]);

        $ordre = $this->actingAs($this->admin)->get('/admin')->viewData('utilisateurs')->pluck('prenom')->all();

        $this->assertSame(['Enligne', 'Recente', 'Ancienne', 'Jamais'], $ordre);
    }

    public function test_le_statut_du_compte_est_affiche_a_cote_de_la_connexion(): void
    {
        $this->utilisateur('Actif', ['derniere_activite' => now()]);
        $this->utilisateur('Bloque', ['derniere_activite' => now()->subHour()], suspendu: true);

        $this->actingAs($this->admin)->get('/admin')->assertSeeInOrder(['Actif', 'text-bg-success', 'Bloque', 'text-bg-danger'], false);
    }

    // ---------- Suivi automatique ----------

    public function test_la_connexion_enregistre_l_activite_et_efface_la_deconnexion(): void
    {
        $u = $this->utilisateur('Awa', ['deconnecte_le' => now()->subHour()]);

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123']);

        $u = $u->fresh();
        $this->assertNull($u->deconnecte_le);
        $this->assertTrue($u->estEnLigne());
    }

    public function test_l_inscription_met_le_nouveau_compte_en_ligne(): void
    {
        $this->post('/register', [
            'nom' => 'Sy', 'prenom' => 'Ali', 'email' => 'ali@example.com',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ]);

        $this->assertTrue(User::where('email', 'ali@example.com')->firstOrFail()->estEnLigne());
    }

    public function test_une_page_visitee_met_a_jour_l_activite_au_plus_une_fois_par_minute(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subSeconds(20)]);
        $avant = $u->derniere_activite->toDateTimeString();

        $this->actingAs($u)->get('/dashboard');
        $this->assertSame($avant, $u->fresh()->derniere_activite->toDateTimeString(), 'moins d\'une minute : pas d\'écriture');

        $u->forceFill(['derniere_activite' => now()->subMinutes(3)])->save();
        $this->actingAs($u->fresh())->get('/dashboard');
        $this->assertTrue($u->fresh()->estEnLigne());
    }

    public function test_un_compte_suspendu_dont_la_session_est_coupee_apparait_deconnecte(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()]);
        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre");

        auth()->logout();
        $this->actingAs($u->fresh())->get('/dashboard')->assertRedirect(route('login'));

        $u = $u->fresh();
        $this->assertNotNull($u->deconnecte_le);
        $this->assertFalse($u->estEnLigne());
    }

    // ---------- Suspension d'un utilisateur en ligne ----------

    public function test_suspendre_un_utilisateur_en_ligne_le_met_hors_ligne_immediatement(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subSeconds(20)]);
        $this->assertTrue($u->estEnLigne());

        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre")->assertSessionHas('success');

        $u = $u->fresh();
        $this->assertSame('suspendu', $u->statut);
        $this->assertFalse($u->estEnLigne(), 'hors ligne dès la suspension, sans attendre sa prochaine requête');
        $this->assertNotNull($u->deconnecte_le);
        $this->assertSame("moins d'une minute", $u->dureeDeconnexion());
    }

    public function test_le_tableau_de_bord_montre_aussitot_le_compte_suspendu_deconnecte(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subSeconds(10)]);
        $this->utilisateur('Moussa', ['derniere_activite' => now()->subSeconds(15)]);

        $avant = $this->actingAs($this->admin)->get('/admin');
        $this->assertSame(2, $avant->viewData('enLigne'));

        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre");
        $apres = $this->actingAs($this->admin)->get('/admin');

        $this->assertSame(1, $apres->viewData('enLigne'));
        $this->assertSame(1, $apres->viewData('suspendus'));
        $apres->assertSee('Suspendu')->assertSee("depuis moins d'une minute");
    }

    public function test_le_compte_suspendu_est_deconnecte_a_sa_prochaine_requete_et_ne_redevient_pas_en_ligne(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subSeconds(20)]);
        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre");
        app('auth')->forgetGuards();

        $this->actingAs($u->fresh())->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Votre compte est suspendu. Contactez l\'administrateur.']);

        $this->assertFalse($u->fresh()->estEnLigne());
    }

    public function test_suspendre_une_personne_deja_deconnectee_garde_sa_duree_de_deconnexion(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subMonths(3)->subMinutes(20), 'deconnecte_le' => now()->subMonths(3)]);
        $avant = $u->deconnecte_le->toDateTimeString();

        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre");

        $this->assertSame($avant, $u->fresh()->deconnecte_le->toDateTimeString());
        $this->assertSame('3 mois', $u->fresh()->dureeDeconnexion());
    }

    public function test_suspendre_un_compte_jamais_connecte_ne_cree_pas_de_fausse_connexion(): void
    {
        $u = $this->utilisateur('Awa');

        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre");

        $this->assertNull($u->fresh()->deconnecte_le);
        $this->assertNull($u->fresh()->derniere_activite);
    }

    public function test_reactiver_un_compte_ne_le_remet_pas_en_ligne(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subSeconds(10)]);
        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/suspendre");
        $this->actingAs($this->admin)->patch("/admin/utilisateurs/{$u->id}/reactiver");

        $this->assertSame('actif', $u->fresh()->statut);
        $this->assertFalse($u->fresh()->estEnLigne(), 'il doit se reconnecter');
    }

    // ---------- Battement de session ----------

    public function test_le_battement_de_session_repond_204_a_une_session_valide_et_compte_comme_activite(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subMinutes(3)]);

        $this->actingAs($u)->get('/session/etat')->assertNoContent();

        $this->assertTrue($u->fresh()->estEnLigne());
    }

    public function test_le_battement_de_session_renvoie_vers_la_connexion_si_le_compte_est_suspendu(): void
    {
        $u = $this->utilisateur('Awa', suspendu: true);

        $this->actingAs($u)->get('/session/etat')->assertRedirect(route('login'));
    }

    public function test_le_battement_de_session_refuse_un_visiteur_non_connecte(): void
    {
        $this->getJson('/session/etat')->assertUnauthorized();
        $this->get('/session/etat')->assertRedirect(route('login'));
    }

    public function test_les_pages_connectees_embarquent_le_battement_de_session(): void
    {
        $u = $this->utilisateur('Awa');
        $html = $this->actingAs($u)->get('/dashboard')->getContent();

        $this->assertStringContainsString('<meta name="sb-session-etat" content="'.route('session.etat').'">', $html);
        $this->assertStringContainsString('<meta name="sb-session-login" content="'.route('login').'">', $html);
        $this->assertStringContainsString('sb-session-etat', file_get_contents(public_path('js/smartbudget.js')));
        $this->assertStringContainsString('setInterval(verifier, 15000)', file_get_contents(public_path('js/smartbudget.js')));
        $this->assertStringNotContainsString('sb-session-etat', $this->get('/login')->getContent(), 'pas de battement sur la page de connexion');
    }

    // ---------- Liste et fiche des utilisateurs ----------

    public function test_la_liste_et_la_fiche_des_utilisateurs_montrent_la_connexion(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()->subMinutes(75)]);

        $this->actingAs($this->admin)->get('/admin/utilisateurs')->assertOk()->assertSee('Connexion')->assertSee('depuis 1 heure et 15 minutes');
        $this->actingAs($this->admin)->get("/admin/utilisateurs/{$u->id}")->assertOk()->assertSee('Déconnecté')->assertSee('Dernière activité');
    }

    // ---------- Confidentialité ----------

    public function test_le_tableau_de_bord_ne_montre_jamais_le_budget_des_utilisateurs(): void
    {
        $u = $this->utilisateur('Awa', ['derniere_activite' => now()]);
        $cat = Categorie::factory()->for($u)->create(['nom' => 'CATEGORIE-PRIVEE']);
        Depense::factory()->create(['user_id' => $u->id, 'categorie_id' => $cat->id, 'description' => 'DEPENSE-PRIVEE', 'montant' => 987654]);

        $html = $this->actingAs($this->admin)->get('/admin')->getContent();

        $this->assertStringNotContainsString('DEPENSE-PRIVEE', $html);
        $this->assertStringNotContainsString('CATEGORIE-PRIVEE', $html);
        $this->assertStringNotContainsString('987 654', $html);
    }

    public function test_la_page_se_rafraichit_toute_seule_toutes_les_30_secondes(): void
    {
        $this->assertStringContainsString('data-actualiser="30000"', $this->actingAs($this->admin)->get('/admin')->getContent());
        $this->assertStringContainsString('data-actualiser', file_get_contents(public_path('js/smartbudget.js')));
    }

    public function test_la_migration_ajoute_les_deux_colonnes_de_suivi(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('users', ['derniere_activite', 'deconnecte_le']));
    }
}
