<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RevenuController;
use App\Http\Requests\CategorieRequest;
use App\Http\Requests\PasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\TransactionRequest;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Traçabilité cahier des charges → code. Chaque exigence structurelle du cahier des charges
 * (§7.1 modèles, §7.2 contrôleurs et méthodes, §7.3 routes, §7.4 middlewares et policies, §7.5 Form Requests,
 * §8.1 vues et composants) est ici une assertion : si un élément venait à manquer, ce test échouerait.
 * Les deux « parcours » en fin de fichier traversent ensuite toutes les exigences fonctionnelles EF1 à EF12 du mémoire.
 */
class ConformiteCahierDesChargesTest extends TestCase
{
    use RefreshDatabase;

    // ---------- §7.1 Modèles Eloquent ----------

    public function test_7_1_modeles_et_relations(): void
    {
        $this->assertInstanceOf(HasMany::class, (new User)->categories());
        $this->assertInstanceOf(HasMany::class, (new User)->depenses());
        $this->assertInstanceOf(HasMany::class, (new User)->revenus());
        $this->assertTrue(method_exists(User::class, 'isAdmin'));

        $this->assertInstanceOf(BelongsTo::class, (new Categorie)->user());
        $this->assertInstanceOf(HasMany::class, (new Categorie)->depenses());
        $this->assertInstanceOf(HasMany::class, (new Categorie)->revenus());

        foreach ([Depense::class, Revenu::class] as $classe) {
            $this->assertInstanceOf(BelongsTo::class, (new $classe)->user());
            $this->assertInstanceOf(BelongsTo::class, (new $classe)->categorie());
        }

        foreach ([User::class => 'users', Categorie::class => 'categories', Depense::class => 'depenses', Revenu::class => 'revenus'] as $modele => $table) {
            $this->assertSame($table, (new $modele)->getTable());
        }
    }

    // ---------- §7.2 Contrôleurs et méthodes ----------

    public function test_7_2_controleurs_et_methodes_du_cahier_des_charges(): void
    {
        $attendu = [
            AuthController::class => ['showRegister', 'register', 'showLogin', 'login', 'logout'],
            ProfilController::class => ['edit', 'update', 'updatePassword'],
            CategorieController::class => ['index', 'store', 'update', 'destroy'],
            DepenseController::class => ['index', 'create', 'store', 'edit', 'update', 'destroy'],
            RevenuController::class => ['index', 'create', 'store', 'edit', 'update', 'destroy'],
            DashboardController::class => ['index', 'statistiques', 'historique'],
            UtilisateurController::class => ['index', 'show', 'edit', 'update', 'suspendre', 'reactiver', 'destroy'],
        ];

        foreach ($attendu as $controleur => $methodes) {
            foreach ($methodes as $methode) {
                $this->assertTrue(method_exists($controleur, $methode), class_basename($controleur)."::$methode manquante");
            }
        }
    }

    // ---------- §7.3 Routes principales ----------

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string}>  [méthode, uri, Contrôleur@action, accès] */
    public static function routesDuCahierDesCharges(): array
    {
        $routes = [
            ['GET', 'register', 'AuthController@showRegister', 'invite'], ['POST', 'register', 'AuthController@register', 'invite'],
            ['GET', 'login', 'AuthController@showLogin', 'invite'], ['POST', 'login', 'AuthController@login', 'invite'],
            ['POST', 'logout', 'AuthController@logout', 'connecte'],
            ['GET', 'dashboard', 'DashboardController@index', 'utilisateur'],
            ['GET', 'statistiques', 'DashboardController@statistiques', 'utilisateur'],
            ['GET', 'historique', 'DashboardController@historique', 'utilisateur'],
            ['GET', 'profil', 'ProfilController@edit', 'connecte'],
            ['PUT', 'profil', 'ProfilController@update', 'connecte'],
            ['PUT', 'profil/password', 'ProfilController@updatePassword', 'connecte'],
            ['GET', 'categories', 'CategorieController@index', 'utilisateur'], ['POST', 'categories', 'CategorieController@store', 'utilisateur'],
            ['PUT', 'categories/{categorie}', 'CategorieController@update', 'utilisateur'], ['DELETE', 'categories/{categorie}', 'CategorieController@destroy', 'utilisateur'],
            ['GET', 'admin/utilisateurs', 'Admin\UtilisateurController@index', 'admin'],
            ['GET', 'admin/utilisateurs/{utilisateur}', 'Admin\UtilisateurController@show', 'admin'],
            ['GET', 'admin/utilisateurs/{utilisateur}/edit', 'Admin\UtilisateurController@edit', 'admin'],
            ['PUT', 'admin/utilisateurs/{utilisateur}', 'Admin\UtilisateurController@update', 'admin'],
            ['DELETE', 'admin/utilisateurs/{utilisateur}', 'Admin\UtilisateurController@destroy', 'admin'],
            ['PATCH', 'admin/utilisateurs/{utilisateur}/suspendre', 'Admin\UtilisateurController@suspendre', 'admin'],
            ['PATCH', 'admin/utilisateurs/{utilisateur}/reactiver', 'Admin\UtilisateurController@reactiver', 'admin'],
        ];
        foreach (['depenses' => 'DepenseController', 'revenus' => 'RevenuController'] as $uri => $controleur) {
            $routes[] = ['GET', $uri, "$controleur@index", 'utilisateur'];
            $routes[] = ['GET', "$uri/create", "$controleur@create", 'utilisateur'];
            $routes[] = ['POST', $uri, "$controleur@store", 'utilisateur'];
            $routes[] = ['GET', "$uri/{transaction}/edit", "$controleur@edit", 'utilisateur'];
            $routes[] = ['PUT', "$uri/{transaction}", "$controleur@update", 'utilisateur'];
            $routes[] = ['DELETE', "$uri/{transaction}", "$controleur@destroy", 'utilisateur'];
        }

        return array_combine(array_map(fn ($r) => "$r[0] /$r[1]", $routes), $routes);
    }

    private function trouverRoute(string $methode, string $uri): ?LaravelRoute
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($methode, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    /** @dataProvider routesDuCahierDesCharges */
    public function test_7_3_chaque_route_existe_avec_la_bonne_action_et_le_bon_acces(string $methode, string $uri, string $action, string $acces): void
    {
        $route = $this->trouverRoute($methode, $uri);

        $this->assertNotNull($route, "Route manquante : $methode /$uri");
        $this->assertSame('App\Http\Controllers\\'.$action, $route->getActionName());

        $middlewares = $route->gatherMiddleware();
        match ($acces) {
            'invite' => $this->assertContains('guest', $middlewares),
            'connecte' => $this->assertContains('auth', $middlewares),
            'utilisateur' => $this->assertSame([], array_diff(['auth', 'actif', 'utilisateur'], $middlewares)),
            'admin' => $this->assertSame([], array_diff(['auth', 'actif', 'admin'], $middlewares)),
        };
        if (in_array($acces, ['connecte', 'utilisateur', 'admin'], true) && $uri !== 'logout') {
            $this->assertContains('actif', $middlewares, 'le compte suspendu doit être déconnecté');
        }
    }

    // ---------- §7.4 Middlewares et autorisations ----------

    public function test_7_4_middlewares_et_policies(): void
    {
        $alias = app(Kernel::class)->getMiddlewareAliases(); // alias de Laravel (auth, guest) et ceux du projet (admin, actif, utilisateur)
        foreach (['auth', 'guest', 'admin', 'actif'] as $nom) {
            $this->assertArrayHasKey($nom, $alias, "middleware « $nom » manquant");
        }

        foreach ([Depense::class => 'DepensePolicy', Revenu::class => 'RevenuPolicy', Categorie::class => 'CategoriePolicy'] as $modele => $policy) {
            $this->assertNotNull(Gate::getPolicyFor($modele), "policy $policy non enregistrée");
            $this->assertSame($policy, class_basename(Gate::getPolicyFor($modele)));
        }
    }

    // ---------- §7.5 Validation (Form Requests) ----------

    public function test_7_5_form_requests_et_regles_principales(): void
    {
        $inscription = (new RegisterRequest)->rules();
        $this->assertContains('confirmed', $inscription['password']);
        $this->assertContains('min:8', $inscription['password']);
        $this->assertContains('max:100', $inscription['nom']);
        $this->assertContains('max:100', $inscription['prenom']);
        $this->assertContains('required', $inscription['email']);

        $mdp = (new PasswordRequest)->rules();
        $this->assertContains('current_password', $mdp['current_password']);
        $this->assertContains('confirmed', $mdp['password']);
        $this->assertContains('min:8', $mdp['password']);

        $this->assertTrue(class_exists(TransactionRequest::class));
        $this->assertTrue(class_exists(CategorieRequest::class));
    }

    // ---------- §8.1 Vues et composants ----------

    public function test_8_1_vues_du_cahier_des_charges(): void
    {
        foreach ([
            'layouts.app', 'layouts.guest', 'auth.login', 'auth.register',
            'dashboard.index', 'dashboard.statistiques', 'dashboard.historique',
            'categories.index', 'profil.edit',
            'admin.utilisateurs.index', 'admin.utilisateurs.show', 'admin.utilisateurs.edit',
            'depenses.index', 'depenses.create', 'depenses.edit', 'revenus.index', 'revenus.create', 'revenus.edit',
            'components.stat-card', 'components.alert-flash', 'components.confirm-modal', 'components.pagination',
        ] as $vue) {
            $this->assertTrue(view()->exists($vue), "vue manquante : $vue");
        }
    }

    /**
     * §8.1 : « depenses / revenus : index (liste + recherche), create, edit ». Chaque dossier existe et ses vues sont
     * bien celles que rendent les contrôleurs ; elles réutilisent la mise en page partagée `transactions/`
     * (mêmes champs et mêmes règles pour les dépenses et les revenus : pas de code dupliqué).
     */
    public function test_8_1_les_dossiers_depenses_et_revenus_rendent_leurs_propres_vues(): void
    {
        $user = User::factory()->create();
        Categorie::factory()->for($user)->create();
        Categorie::factory()->for($user)->revenu()->create();
        $depense = Depense::factory()->create(['user_id' => $user->id]);
        $revenu = Revenu::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        $this->get('/depenses')->assertOk()->assertViewIs('depenses.index');
        $this->get('/depenses/create')->assertOk()->assertViewIs('depenses.create');
        $this->get("/depenses/{$depense->id}/edit")->assertOk()->assertViewIs('depenses.edit');
        $this->get('/revenus')->assertOk()->assertViewIs('revenus.index');
        $this->get('/revenus/create')->assertOk()->assertViewIs('revenus.create');
        $this->get("/revenus/{$revenu->id}/edit")->assertOk()->assertViewIs('revenus.edit');

        // La mise en page partagée est bien utilisée : barre de recherche et tableau (index), formulaire (create, edit)
        $this->get('/depenses')->assertSee('role="search"', false)->assertSee('<table', false);
        $this->get('/revenus/create')->assertSee('name="montant"', false)->assertSee('name="categorie_id"', false);
    }

    // ---------- Parcours complets : EF1 à EF12 ----------

    public function test_parcours_utilisateur_couvre_ef1_a_ef12(): void
    {
        // EF1 Inscription
        $this->post('/register', ['nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'awa@example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
            ->assertRedirect(route('dashboard'));
        $awa = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertSame(13, $awa->categories()->count());

        // EF12 Déconnexion puis EF2 Authentification
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'motdepasse1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($awa);

        // EF3 Gestion du profil (informations puis mot de passe)
        $this->put('/profil', ['nom' => 'Diop', 'prenom' => 'Awa Marie', 'email' => 'awa@example.com'])->assertSessionHasNoErrors();
        $this->put('/profil/password', ['current_password' => 'motdepasse1', 'password' => 'nouveaumdp22', 'password_confirmation' => 'nouveaumdp22'])->assertSessionHasNoErrors();
        $this->assertSame('Awa Marie', $awa->fresh()->prenom);

        // EF6 Gestion des catégories
        $this->post('/categories', ['nom' => 'Cadeaux', 'type' => 'depense'])->assertSessionHasNoErrors();
        $cadeaux = Categorie::where('nom', 'Cadeaux')->firstOrFail();
        $salaire = $awa->categories()->where('nom', 'Salaire')->firstOrFail();
        $alimentation = $awa->categories()->where('nom', 'Alimentation')->firstOrFail();

        // EF4 Revenus et EF5 Dépenses : ajouter, modifier
        $this->post('/revenus', ['montant' => 250000, 'date' => now()->toDateString(), 'categorie_id' => $salaire->id, 'description' => 'Salaire'])->assertSessionHasNoErrors();
        $this->post('/depenses', ['montant' => 5000, 'date' => now()->toDateString(), 'categorie_id' => $alimentation->id, 'description' => 'Marché'])->assertSessionHasNoErrors();
        $this->post('/depenses', ['montant' => 2000, 'date' => now()->toDateString(), 'categorie_id' => $cadeaux->id, 'description' => 'Cadeau'])->assertSessionHasNoErrors();
        $depense = Depense::where('description', 'Marché')->firstOrFail();
        $this->put("/depenses/{$depense->id}", ['montant' => 6000, 'date' => now()->toDateString(), 'categorie_id' => $alimentation->id, 'description' => 'Marché'])->assertSessionHasNoErrors();

        // EF8 Tableau de bord : 250 000 − (6 000 + 2 000) = 242 000
        $this->get('/dashboard')->assertOk()->assertSee('242 000 FCFA')->assertSee('Cadeau');

        // EF9 Statistiques et EF10 Graphiques
        $stats = $this->get('/statistiques')->assertOk()->assertSee('Alimentation')->assertSee('75 %')->assertSee('id="graphique-repartition"', false);
        $this->assertStringContainsString('id="graphique-mensuel"', $stats->getContent());

        // EF7 Historique (revenus et dépenses ensemble) et EF11 Recherche
        $this->get('/historique')->assertOk()->assertSee('Salaire')->assertSee('Marché')->assertSee('Cadeau');
        $this->get('/historique?q=Cadeau&type=depense')->assertSee('Cadeau')->assertDontSee('Marché');
        $this->get('/historique?q=introuvable')->assertSee('Aucune transaction trouvée.');

        // Suppression d'une dépense, puis de la catégorie devenue libre ; une catégorie utilisée reste protégée
        $this->delete('/categories/'.$cadeaux->id)->assertSessionHas('error');
        $this->delete('/depenses/'.Depense::where('description', 'Cadeau')->firstOrFail()->id)->assertSessionHas('success');
        $this->delete('/categories/'.$cadeaux->id)->assertSessionHas('success');

        // EF12 Déconnexion finale
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_parcours_administrateur_gestion_des_comptes(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $moussa = User::factory()->create(['nom' => 'Ndiaye', 'prenom' => 'Moussa', 'email' => 'moussa@example.com']);
        $cat = Categorie::factory()->for($moussa)->create();
        Depense::factory()->count(2)->create(['user_id' => $moussa->id, 'categorie_id' => $cat->id]);

        // Connexion : redirigé vers l'administration (pas vers le tableau de bord)
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password123'])->assertRedirect(route('admin.dashboard'));

        // Lister, rechercher, consulter (date d'inscription et nombre de transactions)
        $this->get('/admin/utilisateurs')->assertOk()->assertSee('Moussa Ndiaye');
        $this->get('/admin/utilisateurs?q=ndiaye')->assertSee('Moussa Ndiaye');
        $this->get("/admin/utilisateurs/{$moussa->id}")->assertOk()->assertSee('Inscrit le')->assertSee('Nombre de transactions');

        // Modifier
        $this->put("/admin/utilisateurs/{$moussa->id}", ['nom' => 'Ndiaye', 'prenom' => 'Moussa Alioune', 'email' => 'moussa@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('Moussa Alioune', $moussa->fresh()->prenom);

        // Suspendre : le compte ne peut plus se connecter ; réactiver : il le peut de nouveau
        $this->patch("/admin/utilisateurs/{$moussa->id}/suspendre")->assertSessionHas('success');
        auth()->logout();
        $this->post('/login', ['email' => 'moussa@example.com', 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($admin)->patch("/admin/utilisateurs/{$moussa->id}/reactiver")->assertSessionHas('success');
        $this->assertSame('actif', $moussa->fresh()->statut);

        // L'administrateur ne peut ni se suspendre ni se supprimer lui-même, ni voir les transactions
        $this->patch("/admin/utilisateurs/{$admin->id}/suspendre")->assertSessionHas('error');
        $this->delete("/admin/utilisateurs/{$admin->id}")->assertSessionHas('error');
        $this->get('/depenses')->assertForbidden();

        // Supprimer : le compte, ses catégories et ses transactions disparaissent
        $this->delete("/admin/utilisateurs/{$moussa->id}")->assertRedirect(route('admin.utilisateurs.index'));
        $this->assertModelMissing($moussa);
        $this->assertSame(0, Depense::count() + Categorie::count());
        $this->assertModelExists($admin);
    }
}
