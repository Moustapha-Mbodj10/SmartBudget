<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Module 9 — Revue de sécurité transverse (CDC §9.1) : CSRF, XSS, sessions, en-têtes, couverture des routes. */
class SecuriteTest extends TestCase
{
    use RefreshDatabase;

    private const CHARGES = [
        '<script>alert(1)</script>',
        '"><img src=x onerror=alert(1)>',
        '</script><script>alert(2)</script>',
        '<!--<script>alert(3)</script>',
        "'; alert(4); //",
    ];

    // ---------- Couverture des routes ----------

    /** @return list<LaravelRoute> */
    private function routesApplication(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $r) => ! str_starts_with($r->uri(), 'storage') && $r->uri() !== 'up')
            ->values()->all();
    }

    public function test_seules_les_pages_d_authentification_sont_accessibles_sans_connexion(): void
    {
        $publiques = ['/', 'login', 'register']; // « / » ne fait que rediriger
        $anormales = [];

        foreach ($this->routesApplication() as $route) {
            $middlewares = $route->gatherMiddleware();
            $estProtegee = in_array('auth', $middlewares, true);
            $estPublique = in_array($route->uri(), $publiques, true);

            if (! $estProtegee && ! $estPublique) {
                $anormales[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $anormales, 'Routes accessibles sans connexion :'.PHP_EOL.implode(PHP_EOL, $anormales));
    }

    public function test_toutes_les_routes_d_administration_exigent_le_role_admin(): void
    {
        $manquantes = [];
        foreach ($this->routesApplication() as $route) {
            if (str_starts_with($route->uri(), 'admin/') && ! in_array('admin', $route->gatherMiddleware(), true)) {
                $manquantes[] = $route->uri();
            }
        }

        $this->assertSame([], $manquantes);
    }

    public function test_toutes_les_routes_budget_sont_reservees_aux_comptes_utilisateur(): void
    {
        $budget = ['dashboard', 'statistiques', 'historique', 'depenses', 'revenus', 'categories'];
        $manquantes = [];

        foreach ($this->routesApplication() as $route) {
            $racine = explode('/', $route->uri())[0];
            if (in_array($racine, $budget, true) && ! in_array('utilisateur', $route->gatherMiddleware(), true)) {
                $manquantes[] = $route->uri();
            }
        }

        $this->assertSame([], $manquantes, 'Routes budget sans le middleware « utilisateur »');
    }

    public function test_toutes_les_routes_connectees_verifient_que_le_compte_est_actif(): void
    {
        $sansActif = [];
        foreach ($this->routesApplication() as $route) {
            $mw = $route->gatherMiddleware();
            // La déconnexion reste possible même pour un compte suspendu
            if (in_array('auth', $mw, true) && $route->uri() !== 'logout' && ! in_array('actif', $mw, true)) {
                $sansActif[] = $route->uri();
            }
        }

        $this->assertSame([], $sansActif);
    }

    public function test_aucune_modification_n_est_possible_en_get(): void
    {
        // Toute route GET ne doit pas modifier de données : on vérifie qu'aucune route d'écriture n'accepte GET
        foreach ($this->routesApplication() as $route) {
            if (str_contains($route->uri(), 'suspendre') || str_contains($route->uri(), 'reactiver') || $route->uri() === 'logout') {
                $this->assertNotContains('GET', $route->methods(), $route->uri());
            }
        }
        $this->get('/logout')->assertStatus(405);
    }

    // ---------- CSRF ----------

    public function test_chaque_formulaire_non_get_contient_un_jeton_csrf(): void
    {
        $manquants = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('#<form\b[^>]*>(.*?)</form>#is', file_get_contents($fichier->getPathname()), $formulaires, PREG_SET_ORDER);

            foreach ($formulaires as $f) {
                $estGet = (bool) preg_match('/method\s*=\s*"get"/i', $f[0]);
                if (! $estGet && ! str_contains($f[0], '@csrf')) {
                    $manquants[] = $fichier->getFilename();
                }
            }
        }

        $this->assertSame([], $manquants, 'Formulaires sans @csrf');
    }

    public function test_les_pages_avec_formulaire_envoient_un_vrai_jeton_csrf(): void
    {
        $user = User::factory()->create();
        Categorie::factory()->for($user)->create();

        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertSee('name="_token"', false);
        }
        foreach (['/profil', '/categories', '/depenses/create', '/revenus/create'] as $url) {
            $this->actingAs($user)->get($url)->assertSee('name="_token"', false);
        }
    }

    // ---------- XSS ----------

    public function test_aucune_sortie_html_non_echappee_dans_les_vues(): void
    {
        $trouvees = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $fichier) {
            if ($fichier->isFile() && str_contains(file_get_contents($fichier->getPathname()), '{!!')) {
                $trouvees[] = $fichier->getFilename();
            }
        }

        $this->assertSame([], $trouvees, 'Les vues doivent échapper toutes les sorties avec {{ }}');
    }

    /** @dataProvider provenanceCharges */
    public function test_les_donnees_saisies_sont_echappees_sur_toutes_les_pages(string $charge): void
    {
        $user = User::factory()->create(['nom' => $charge, 'prenom' => $charge]);
        $cat = Categorie::factory()->for($user)->create(['nom' => 'C'.$charge, 'description' => $charge]);
        $rev = Categorie::factory()->for($user)->revenu()->create(['nom' => 'R'.$charge]);
        Depense::factory()->create(['user_id' => $user->id, 'categorie_id' => $cat->id, 'description' => $charge, 'date' => now()->toDateString()]);
        Revenu::factory()->create(['user_id' => $user->id, 'categorie_id' => $rev->id, 'description' => $charge, 'date' => now()->toDateString()]);
        $admin = User::factory()->admin()->create();

        $pagesUtilisateur = ['/dashboard', '/statistiques', '/historique', '/depenses', '/revenus', '/categories', '/profil',
            '/depenses/create', '/revenus/create', '/historique?q='.urlencode($charge), '/depenses?q='.urlencode($charge)];

        foreach ($pagesUtilisateur as $url) {
            $this->assertEchappe($this->actingAs($user)->get($url)->getContent(), $charge, $url);
        }
        foreach (['/admin/utilisateurs', "/admin/utilisateurs/{$user->id}", "/admin/utilisateurs/{$user->id}/edit", '/admin/utilisateurs?q='.urlencode($charge)] as $url) {
            $this->assertEchappe($this->actingAs($admin)->get($url)->getContent(), $charge, $url);
        }
    }

    public static function provenanceCharges(): array
    {
        return array_combine(array_map(fn ($c) => substr($c, 0, 28), self::CHARGES), array_map(fn ($c) => [$c], self::CHARGES));
    }

    /** La charge ne doit jamais apparaître telle quelle dans le HTML (hors données JSON, vérifiées à part). */
    private function assertEchappe(string $html, string $charge, string $url): void
    {
        // On retire les blocs <script> JSON/JS : ils ont leur propre test (pas de « < » brut à l'intérieur)
        $sansScripts = preg_replace('#<script\b.*?</script>#is', '', $html);

        $this->assertStringNotContainsString($charge, $sansScripts, "Charge non échappée sur $url");
        $this->assertStringNotContainsString('<script>alert', $sansScripts, "Balise <script> injectée sur $url");
        $this->assertStringNotContainsString('<img src=x', $sansScripts, "Balise <img> injectée sur $url");
    }

    /** @dataProvider provenanceCharges */
    public function test_les_donnees_injectees_dans_les_balises_script_ne_peuvent_pas_en_sortir(string $charge): void
    {
        $nbScripts = fn (string $html) => preg_match_all('#<script#i', $html);

        // 1) Camembert : le nom de la catégorie part dans un bloc JSON (tableau de bord et statistiques)
        $sain = User::factory()->create();
        $catSaine = Categorie::factory()->for($sain)->create(['nom' => 'Alimentation']);
        Depense::factory()->create(['user_id' => $sain->id, 'categorie_id' => $catSaine->id, 'date' => now()->toDateString(), 'montant' => 5000]);

        $piege = User::factory()->create();
        $catPiege = Categorie::factory()->for($piege)->create(['nom' => $charge]);
        Depense::factory()->create(['user_id' => $piege->id, 'categorie_id' => $catPiege->id, 'date' => now()->toDateString(), 'montant' => 5000]);

        foreach (['/dashboard', '/statistiques'] as $url) {
            $reference = $this->actingAs($sain)->get($url)->getContent();
            $html = $this->actingAs($piege)->get($url)->getContent();

            preg_match('#<script type="application/json" id="donnees-graphiques">(.*?)</script>#s', $html, $m);
            $this->assertNotEmpty($m, "bloc JSON absent sur $url");
            $this->assertStringNotContainsString('<', $m[1], "« < » brut dans le JSON de $url");
            $this->assertStringNotContainsString('>', $m[1], "« > » brut dans le JSON de $url");
            $this->assertSame($charge, json_decode($m[1], true)['repartition'][0]['categorie'], 'le nom reste lisible une fois décodé');
            $this->assertSame($nbScripts($reference), $nbScripts($html), "une balise <script> a été injectée sur $url");
            $this->assertStringNotContainsString('<script>alert', $html);
        }

        // 2) Fenêtre modale des catégories : après une erreur, les valeurs saisies sont réinjectées dans le JavaScript
        $reference = $this->actingAs($sain)->get('/categories')->getContent();
        $this->actingAs($piege)->from('/categories')->post('/categories', [
            '_form' => 'create', 'type' => 'depense', 'nom' => $charge, 'description' => $charge.str_repeat('x', 300),
        ])->assertSessionHasErrors('description');
        $html = $this->actingAs($piege)->get('/categories')->getContent();

        $this->assertStringContainsString('ouvrir(', $html);
        $this->assertSame($nbScripts($reference), $nbScripts($html), 'une balise <script> a été injectée sur /categories');
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<!--<script>', $html);
    }

    // ---------- Sessions et cookies ----------

    public function test_le_cookie_de_session_est_httponly_et_samesite(): void
    {
        $reponse = $this->get('/login');
        $cookie = collect($reponse->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie, 'cookie de session absent');
        $this->assertTrue($cookie->isHttpOnly(), 'le cookie de session doit être HttpOnly');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame(120, (int) config('session.lifetime'));
    }

    public function test_la_session_est_regeneree_a_la_connexion_et_detruite_a_la_deconnexion(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        $this->get('/login');
        $avant = session()->getId();

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'password123']);
        $this->assertNotSame($avant, session()->getId());

        $apres = session()->getId();
        $this->post('/logout');
        $this->assertNotSame($apres, session()->getId());
        $this->assertGuest();
    }

    // ---------- Mots de passe ----------

    public function test_le_mot_de_passe_n_est_jamais_expose(): void
    {
        $user = User::factory()->create(['password' => 'secretissime1']);

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertStringNotContainsString('password', $user->toJson());

        foreach (['/profil', '/dashboard'] as $url) {
            $html = $this->actingAs($user)->get($url)->getContent();
            $this->assertStringNotContainsString('secretissime1', $html);
            $this->assertStringNotContainsString($user->password, $html);
        }
        $this->assertStringStartsWith('$2y$', $user->password);
    }

    public function test_les_champs_mot_de_passe_ne_sont_jamais_pre_remplis(): void
    {
        $user = User::factory()->create();

        $this->from('/register')->post('/register', ['nom' => 'A', 'prenom' => 'B', 'email' => 'pas-un-email', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1']);
        $this->get('/register')->assertDontSee('motdepasse1');

        $this->actingAs($user)->from('/profil')->put('/profil/password', ['current_password' => 'faux', 'password' => 'nouveaumdp2', 'password_confirmation' => 'nouveaumdp2']);
        $this->actingAs($user)->get('/profil')->assertDontSee('nouveaumdp2');
    }

    // ---------- En-têtes HTTP ----------

    public function test_les_en_tetes_de_securite_sont_envoyes(): void
    {
        $reponse = $this->get('/login');

        $this->assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $reponse->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $reponse->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=()', $reponse->headers->get('Permissions-Policy'));
    }

    public function test_les_en_tetes_de_securite_couvrent_aussi_les_redirections_et_les_erreurs(): void
    {
        $this->get('/dashboard')->assertRedirect()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create())->get('/admin/utilisateurs')->assertForbidden()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->get('/page-qui-n-existe-pas')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_les_pages_connectees_ne_sont_pas_mises_en_cache(): void
    {
        // Poste partagé (cybercafé) : après déconnexion, le bouton « retour » ne doit pas réafficher des données financières
        $reponse = $this->actingAs(User::factory()->create())->get('/dashboard');

        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
    }

    // ---------- Pages d'erreur ----------

    public function test_les_pages_d_erreur_sont_en_francais_et_sans_detail_technique(): void
    {
        config(['app.debug' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/utilisateurs')->assertForbidden()->assertSee('Accès refusé');
        $this->get('/page-qui-n-existe-pas')->assertNotFound()->assertSee('Page introuvable');
    }

    public function test_une_erreur_interne_n_expose_ni_trace_ni_chemins_ni_requetes(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test-erreur', fn () => throw new \RuntimeException('secret-interne /var/www/.env SELECT * FROM users'))->middleware('web');

        $reponse = $this->get('/_test-erreur');

        $reponse->assertStatus(500)->assertSee('Une erreur est survenue');
        $reponse->assertDontSee('secret-interne')->assertDontSee('SELECT * FROM')->assertDontSee('RuntimeException')->assertDontSee('.env');
        $this->assertStringNotContainsString('vendor/laravel', $reponse->getContent());
    }

    public function test_la_page_session_expiree_419_est_en_francais(): void
    {
        Route::get('/_test-419', fn () => abort(419))->middleware('web');

        $this->get('/_test-419')->assertStatus(419)->assertSee('Session expirée');
    }

    // ---------- Limitation de débit ----------

    public function test_l_inscription_est_limitee_en_debit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->post('/register', ['nom' => 'A'])->assertStatus(302); // refusée par la validation, mais comptée
        }

        $this->post('/register', ['nom' => 'A'])->assertStatus(429);
    }
}
