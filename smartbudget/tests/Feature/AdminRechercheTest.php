<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Page de l'administrateur dédiée à la recherche d'un utilisateur. */
class AdminRechercheTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'nom' => 'Zzadmin', 'prenom' => 'Zzsuper']);
    }

    private function compte(string $prenom, string $nom, string $email, array $suivi = [], bool $suspendu = false): User
    {
        $u = User::factory()->create(['prenom' => $prenom, 'nom' => $nom, 'email' => $email]);
        if ($suspendu) {
            $u->statut = User::STATUT_SUSPENDU;
        }
        $u->forceFill($suivi)->save();

        return $u->fresh();
    }

    private function chercher(array $parametres = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.recherche', $parametres));
    }

    // ---------- Accès et menu ----------

    public function test_la_page_est_reservee_a_l_administrateur(): void
    {
        $this->get('/admin/recherche')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/recherche')->assertForbidden();
        $this->chercher()->assertOk()->assertSee('Rechercher');
    }

    public function test_un_menu_rechercher_existe_pour_l_administrateur_seulement(): void
    {
        $admin = $this->chercher()->getContent();
        $this->assertStringContainsString('href="'.route('admin.recherche').'"', $admin);
        $this->assertMatchesRegularExpression('/class="nav-link active"\s+href="[^"]*\/admin\/recherche"/', $admin);
        $this->assertStringContainsString('class="sb-rail-link active" title="Rechercher un utilisateur"', $admin);

        $utilisateur = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();
        $this->assertStringNotContainsString(route('admin.recherche'), $utilisateur);
    }

    public function test_les_autres_pages_de_l_administrateur_proposent_aussi_le_menu(): void
    {
        foreach (['/admin', '/admin/utilisateurs', '/profil'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertSee(route('admin.recherche'), false);
        }
    }

    // ---------- Sans recherche ----------

    public function test_sans_recherche_aucun_utilisateur_n_est_liste(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');

        $this->chercher()->assertOk()
            ->assertSee('Tapez un nom, un prénom ou une adresse e-mail')
            ->assertDontSee('awa@example.com');
    }

    // ---------- Résultats ----------

    public function test_recherche_par_nom_prenom_email_et_nom_complet(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');
        $this->compte('Moussa', 'Ndiaye', 'moussa@example.com');

        foreach (['diop', 'AWA', 'moussa@exa', 'Awa Diop', 'Diop Awa', 'example.com'] as $terme) {
            $reponse = $this->chercher(['q' => $terme])->assertOk();
            $this->assertGreaterThanOrEqual(1, $reponse->viewData('resultats')->total(), $terme);
        }
        $this->chercher(['q' => 'diop'])->assertSee('awa@example.com', false)->assertDontSee('moussa@example.com');
        $this->assertSame(3, $this->chercher(['q' => 'example.com'])->viewData('resultats')->total(), "les deux comptes et l'administrateur");
    }

    public function test_aucun_resultat_affiche_un_message(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');

        $this->chercher(['q' => 'introuvable'])->assertOk()->assertSee('Aucun utilisateur ne correspond à votre recherche');
    }

    public function test_le_terme_trouve_est_surligne_et_le_html_reste_echappe(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');

        $html = $this->chercher(['q' => 'dio'])->getContent();

        $this->assertStringContainsString('<mark>Dio</mark>p', $html);
    }

    public function test_les_pourcent_et_underscore_sont_du_texte(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');
        $this->compte('Pa%ul', 'Sy', 'paul@example.com');

        $this->assertSame(0, $this->chercher(['q' => '%'])->viewData('resultats')->where('email', 'awa@example.com')->count());
        $this->assertSame(1, $this->chercher(['q' => 'Pa%ul'])->viewData('resultats')->total());
        $this->assertSame(0, $this->chercher(['q' => 'A_a'])->viewData('resultats')->total());
    }

    public function test_une_injection_dans_la_recherche_est_inoffensive(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');
        $this->compte('<script>alert(1)</script>', 'Pirate', 'pirate@example.com');

        $html = $this->chercher(['q' => '<script>alert(1)</script>'])->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->chercher(['q' => "' OR 1=1 --"])->assertOk()->assertSee('Aucun utilisateur ne correspond');
    }

    public function test_les_termes_avec_caracteres_speciaux_de_regex_ne_cassent_pas_le_surlignage(): void
    {
        $this->compte('Awa', 'Diop (test)', 'awa@example.com');

        $this->chercher(['q' => '(test)'])->assertOk()->assertSee('<mark>(test)</mark>', false);
        $this->chercher(['q' => '['])->assertOk();
    }

    // ---------- Filtres ----------

    public function test_filtre_par_statut_du_compte(): void
    {
        $this->compte('Actif', 'Un', 'actif@example.com');
        $this->compte('Bloque', 'Deux', 'bloque@example.com', suspendu: true);

        $this->assertSame(['bloque@example.com'], $this->chercher(['statut' => 'suspendu'])->viewData('resultats')->pluck('email')->all());
        $this->assertContains('actif@example.com', $this->chercher(['statut' => 'actif'])->viewData('resultats')->pluck('email')->all());
        $this->assertNotContains('bloque@example.com', $this->chercher(['statut' => 'actif'])->viewData('resultats')->pluck('email')->all());
    }

    public function test_filtre_en_ligne_et_deconnectes(): void
    {
        $this->compte('Enligne', 'Un', 'enligne@example.com', ['derniere_activite' => now()->subMinute()]);
        $this->compte('Parti', 'Deux', 'parti@example.com', ['derniere_activite' => now()->subHours(2), 'deconnecte_le' => now()->subHour()]);
        $this->compte('Expire', 'Trois', 'expire@example.com', ['derniere_activite' => now()->subDays(2)]);
        $this->compte('Jamais', 'Quatre', 'jamais@example.com');

        $enLigne = $this->chercher(['connexion' => 'en_ligne'])->viewData('resultats')->pluck('email')->all();
        // L'administrateur qui fait la recherche est lui-même en ligne pendant sa requête
        $this->assertEqualsCanonicalizing(['enligne@example.com', 'admin@example.com'], $enLigne);

        $deconnectes = $this->chercher(['connexion' => 'deconnecte'])->viewData('resultats')->pluck('email')->all();
        $this->assertEqualsCanonicalizing(['parti@example.com', 'expire@example.com', 'jamais@example.com'], $deconnectes);
    }

    public function test_les_filtres_se_combinent_avec_le_texte(): void
    {
        $this->compte('Awa', 'Un', 'awa.un@example.com', ['derniere_activite' => now()]);
        $this->compte('Awa', 'Deux', 'awa.deux@example.com', ['derniere_activite' => now()], suspendu: true);
        $this->compte('Moussa', 'Trois', 'moussa@example.com', ['derniere_activite' => now()]);

        $r = $this->chercher(['q' => 'awa', 'statut' => 'actif', 'connexion' => 'en_ligne'])->viewData('resultats');

        $this->assertSame(['awa.un@example.com'], $r->pluck('email')->all());
    }

    public function test_des_valeurs_de_filtre_invalides_sont_ignorees(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com');

        $reponse = $this->chercher(['q' => 'awa', 'statut' => 'nimporte', 'connexion' => 'quoi'])->assertOk();

        $this->assertSame(1, $reponse->viewData('resultats')->total());
        $this->assertSame('', $reponse->viewData('statut'));
        $this->assertSame('', $reponse->viewData('connexion'));
    }

    // ---------- Affichage d'un résultat ----------

    public function test_un_resultat_montre_statut_connexion_duree_et_liens(): void
    {
        $u = $this->compte('Awa', 'Diop', 'awa@example.com', ['derniere_activite' => now()->subMinutes(135)]);

        $this->chercher(['q' => 'awa'])
            ->assertSee('Utilisateur')->assertSee('Actif')
            ->assertSee('Déconnecté')->assertSee('depuis 2 heures et 15 minutes')
            ->assertSee(route('admin.utilisateurs.show', $u), false)
            ->assertSee(route('admin.utilisateurs.edit', $u), false);
    }

    public function test_un_utilisateur_en_ligne_est_signale_en_ligne(): void
    {
        $this->compte('Awa', 'Diop', 'awa@example.com', ['derniere_activite' => now()->subSeconds(30)]);

        $this->chercher(['q' => 'awa'])->assertSee('En ligne');
    }

    public function test_la_recherche_trouve_aussi_les_administrateurs_avec_leur_role(): void
    {
        $this->chercher(['q' => 'Zzadmin'])->assertSee('Administrateur')->assertSee('Zzadmin', false);
    }

    // ---------- Pagination ----------

    public function test_pagination_par_10_en_conservant_la_recherche(): void
    {
        foreach (range(1, 14) as $i) {
            $this->compte('Pers'.sprintf('%02d', $i), 'Dupont', "pers$i@example.com");
        }

        $page1 = $this->chercher(['q' => 'Dupont'])->assertOk();
        $this->assertSame(14, $page1->viewData('resultats')->total());
        $this->assertCount(10, $page1->viewData('resultats')->items());
        $page1->assertSee('q=Dupont', false);

        $this->assertCount(4, $this->chercher(['q' => 'Dupont', 'page' => 2])->viewData('resultats')->items());
    }

    // ---------- Confidentialité ----------

    public function test_la_page_ne_montre_jamais_le_budget_des_utilisateurs(): void
    {
        $u = $this->compte('Awa', 'Diop', 'awa@example.com');
        $cat = Categorie::factory()->for($u)->create(['nom' => 'CATEGORIE-PRIVEE']);
        Depense::factory()->create(['user_id' => $u->id, 'categorie_id' => $cat->id, 'description' => 'DEPENSE-PRIVEE', 'montant' => 987654]);

        $html = $this->chercher(['q' => 'awa'])->getContent();

        foreach (['DEPENSE-PRIVEE', 'CATEGORIE-PRIVEE', '987 654'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_le_formulaire_est_un_get_accessible_avec_le_champ_en_autofocus(): void
    {
        $html = $this->chercher()->getContent();

        $this->assertMatchesRegularExpression('~<form method="GET" action="[^"]*admin/recherche" role="search"~', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('autofocus', $html);
        $this->assertStringContainsString('<label for="q" class="visually-hidden">', $html);
    }

    public function test_le_helper_de_surlignage(): void
    {
        $this->assertSame('<mark>Aw</mark>a', (string) surligner('Awa', 'aw'));
        $this->assertSame('Awa', (string) surligner('Awa', ''));
        $this->assertSame('&lt;b&gt;', (string) surligner('<b>', ''));
        $this->assertSame('O&#039;<mark>Neil</mark>', (string) surligner("O'Neil", 'neil'));
        $this->assertSame("O&#039;Neil", (string) surligner("O'Neil", '039'), 'une entité HTML n\'est jamais coupée');
    }
}
