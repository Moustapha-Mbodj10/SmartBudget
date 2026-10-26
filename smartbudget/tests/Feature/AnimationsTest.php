<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Animations (inspirées de 21st.dev, réécrites en CSS / JS natifs) : barre de recherche extensible et aperçu animé
 * de la page de connexion. Le comportement visuel est mesuré dans un vrai navigateur (tests/Navigateur) ;
 * ces tests vérifient le balisage, l'accessibilité, la dégradation sans JavaScript et l'absence d'injection.
 */
class AnimationsTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Barre de recherche extensible ----------

    public function test_la_page_des_categories_a_une_barre_de_recherche_extensible_accessible(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/categories')->getContent();

        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('data-recherche', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('aria-label="Rechercher une catégorie"', $html);
        $this->assertStringContainsString('aria-label="Ouvrir la recherche"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html, 'repliée tant qu\'aucune recherche n\'est en cours');
        $this->assertStringContainsString('aria-label="Rechercher"', $html, 'bouton de validation conservé');
    }

    public function test_sans_javascript_la_recherche_reste_un_formulaire_get_complet(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/categories')->getContent();

        // Le formulaire fonctionne tel quel : méthode GET, champ « q », bouton d'envoi, placeholder lisible
        $this->assertMatchesRegularExpression('~<form method="GET" action="[^"]*categories" role="search"~', $html);
        $this->assertStringContainsString('placeholder="Rechercher une catégorie"', $html);
        $this->assertStringContainsString('type="submit" class="sb-search-go"', $html);

        // Les états « replié » ne s'appliquent qu'avec JavaScript (classe « js » ajoutée par la page)
        $css = file_get_contents(public_path('css/smartbudget.css'));
        $this->assertStringContainsString('.js .sb-search:not(.is-open) .sb-search-champ { width: 0;', $css);
        $this->assertStringNotContainsString("\n.sb-search:not(.is-open) .sb-search-champ", $css);
    }

    public function test_les_suggestions_sont_les_noms_des_categories_de_l_utilisateur(): void
    {
        $user = User::factory()->create();
        Categorie::factory()->for($user)->create(['nom' => 'Alimentation']);
        Categorie::factory()->for($user)->revenu()->create(['nom' => 'Salaire']);
        Categorie::factory()->for(User::factory()->create())->create(['nom' => 'CategorieDeLAutre']);

        $html = $this->actingAs($user)->get('/categories')->getContent();

        $this->assertStringContainsString('data-suggestions="[&quot;Alimentation&quot;,&quot;Salaire&quot;]"', $html);
        $this->assertStringNotContainsString('CategorieDeLAutre', $html);
        $this->assertStringContainsString('data-prefixe="Ex. : "', $html);
    }

    public function test_les_suggestions_ne_permettent_aucune_injection(): void
    {
        $user = User::factory()->create();
        Categorie::factory()->for($user)->create(['nom' => '"><script>alert(1)</script>']);

        $html = $this->actingAs($user)->get('/categories')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('"><script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_une_recherche_en_cours_garde_la_barre_ouverte_avec_un_bouton_pour_l_effacer(): void
    {
        $user = User::factory()->create();
        Categorie::factory()->for($user)->create(['nom' => 'Transport']);

        $html = $this->actingAs($user)->get('/categories?q=Trans')->getContent();

        $this->assertStringContainsString('sb-search is-open has-value', $html);
        $this->assertStringContainsString('value="Trans"', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringContainsString('title="Effacer la recherche"', $html);
    }

    public function test_la_recherche_des_utilisateurs_de_l_administration_est_aussi_extensible(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/utilisateurs')->getContent();

        $this->assertStringContainsString('data-recherche', $html);
        $this->assertStringContainsString('aria-label="Rechercher un utilisateur"', $html);
        $this->assertStringContainsString('data-prefixe="Chercher par "', $html);
        $this->assertStringContainsString('data-suggestions="[&quot;nom&quot;,&quot;pr', $html);
    }

    public function test_la_recherche_fonctionne_toujours_comme_avant(): void
    {
        $user = User::factory()->create();
        Categorie::factory()->for($user)->create(['nom' => 'Alimentation']);
        Categorie::factory()->for($user)->create(['nom' => 'Transport']);

        $this->actingAs($user)->get('/categories?q=alim')->assertSee('Alimentation')->assertDontSee('Transport');
    }

    // ---------- Aperçu animé de la page de connexion (cartes d'interface qui flottent) ----------

    public function test_la_page_de_connexion_affiche_l_apercu_anime_decoratif(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('data-mascotte', $html);
        $this->assertStringContainsString('class="sb-ap ', $html);
        $this->assertStringContainsString('aria-hidden="true" data-mascotte', $html, "décoratif : masqué aux lecteurs d'écran");
        foreach (['sb-c1', 'sb-c2', 'sb-c3', 'sb-c4', 'sb-c5', 'sb-ap-trait', 'sb-arc-rouge', 'sb-ap-barre sb-bb2'] as $morceau) {
            $this->assertStringContainsString($morceau, $html, $morceau);
        }
        // Les montants d'exemple sont ceux qui se floutent pendant la saisie du mot de passe
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'sb-v'));
        $this->assertStringNotContainsString('<img', $html, 'aucune image à charger : tout est dessiné en HTML / SVG');
    }

    public function test_la_page_d_inscription_affiche_aussi_l_apercu_anime(): void
    {
        $this->get('/register')->assertSee('data-mascotte', false)->assertSee('data-connexion', false);
    }

    public function test_le_bouton_afficher_le_mot_de_passe_est_accessible(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('data-afficher-mdp', $html);
        $this->assertStringContainsString('type="button" class="sb-pw-toggle"', $html, 'ne soumet pas le formulaire');
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('aria-label="Afficher le mot de passe"', $html);
        $this->assertMatchesRegularExpression('~<input type="password" id="password" name="password" data-mdp~', $html);
    }

    public function test_a_l_inscription_un_seul_bouton_commande_les_deux_champs_mot_de_passe(): void
    {
        $html = $this->get('/register')->getContent();

        $this->assertSame(1, substr_count($html, 'data-afficher-mdp'));
        $this->assertSame(2, substr_count($html, 'data-mdp'));
    }

    public function test_les_cartes_tremblent_quand_le_formulaire_revient_en_erreur(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $this->from('/login')->post('/login', ['email' => 'awa@example.com', 'password' => 'faux']);
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('class="sb-ap is-error"', $html);
        $this->assertStringContainsString('Mot de passe incorrect.', $html);
    }

    public function test_l_apercu_est_calme_sans_erreur(): void
    {
        $this->assertStringNotContainsString('is-error', $this->get('/login')->getContent());
    }

    public function test_un_mot_de_passe_n_est_jamais_prerempli_malgre_le_bouton_afficher(): void
    {
        $this->from('/login')->post('/login', ['email' => 'a@example.com', 'password' => 'secret-tres-prive']);

        $this->get('/login')->assertDontSee('secret-tres-prive');
    }

    // ---------- Chargement et respect de « réduire les animations » ----------

    public function test_les_pages_chargent_le_script_local_et_activent_le_mode_js(): void
    {
        $user = User::factory()->create();

        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertStringContainsString("classList.add('js')", $html, $url);
            $this->assertStringContainsString('js/animations.js', $html, $url);
        }
        $this->assertStringContainsString('js/animations.js', $this->actingAs($user)->get('/categories')->getContent());
    }

    public function test_le_script_n_appelle_aucun_service_externe(): void
    {
        $js = file_get_contents(public_path('js/animations.js'));

        $this->assertStringNotContainsString('https://', $js);
        $this->assertStringNotContainsString('http://', $js);
        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
    }

    public function test_le_script_et_la_feuille_de_style_respectent_reduire_les_animations(): void
    {
        $js = file_get_contents(public_path('js/animations.js'));
        $css = file_get_contents(public_path('css/smartbudget.css'));

        $this->assertStringContainsString('(prefers-reduced-motion: reduce)', $js);
        $this->assertStringContainsString('if (!reduit && textes.length > 1)', $js, 'pas de défilement des suggestions');
        $this->assertStringContainsString('if (formulaire && !reduit)', $js, 'pas de délai avant l\'envoi du formulaire');
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_le_formulaire_de_connexion_n_est_jamais_bloque_par_l_animation(): void
    {
        $js = file_get_contents(public_path('js/animations.js'));

        // La pièce tombe pendant 520 ms puis le formulaire part, une seule fois (pas de double envoi)
        $this->assertStringContainsString('setTimeout(function () { formulaire.submit(); }, 520);', $js);
        $this->assertStringContainsString('if (envoye) { return; }', $js);
    }

    // ---------- Logo et couleurs de la marque ----------

    public function test_le_logo_remplace_l_icone_de_tirelire_dans_toutes_les_pages(): void
    {
        $user = User::factory()->create();

        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertStringContainsString('<span class="sb-logo"><svg viewBox="395 300 300 360"', $html, $url);
            $this->assertStringNotContainsString('bi-piggy-bank', $html, $url);
        }
        $html = $this->actingAs($user)->get('/dashboard')->getContent();
        $this->assertStringContainsString('<span class="sb-logo"><svg', $html);
        $this->assertStringNotContainsString('bi-piggy-bank', $html);
    }

    public function test_le_logo_d_origine_et_l_icone_d_onglet_sont_fournis_en_local(): void
    {
        $this->assertFileExists(public_path('img/logo-smartbudget.jpg'));
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertStringContainsString('#054086', file_get_contents(public_path('favicon.svg')));
        $this->assertStringContainsString('favicon.svg', $this->get('/login')->getContent());
        $this->assertStringContainsString('favicon.svg', $this->actingAs(User::factory()->create())->get('/dashboard')->getContent());
    }

    public function test_la_feuille_de_style_reprend_le_bleu_et_le_vert_menthe_du_logo(): void
    {
        $css = file_get_contents(public_path('css/smartbudget.css'));

        $this->assertStringContainsString('--sb-brand: #054086;', $css);
        $this->assertStringContainsString('--sb-mint: #52eba8;', $css);
        $this->assertStringContainsString('--bs-primary: #054086;', $css);
        // Les revenus restent verts et les dépenses rouges : la couleur de la marque ne change pas le sens des montants
        $this->assertStringContainsString('.sb-positive { color: var(--sb-green-deep); }', $css);
        $this->assertStringContainsString('.sb-negative { color: var(--sb-red); }', $css);
    }

    public function test_les_etats_finaux_de_l_apercu_restent_visibles_si_les_animations_sont_coupees(): void
    {
        $css = file_get_contents(public_path('css/smartbudget.css'));

        // Aucun état « caché » dans les règles de base : l'invisible n'existe que dans les images clés (désactivées par reduce)
        $this->assertStringNotContainsString('.sb-ap-trait { stroke-dasharray: 1; stroke-dashoffset: 1', $css);
        $this->assertStringContainsString('@keyframes sb-trace { from { stroke-dashoffset: 1; } to { stroke-dashoffset: 0; } }', $css);
        $this->assertStringContainsString('animation: none !important', $css);
    }

    public function test_la_connexion_fonctionne_toujours_avec_le_nouveau_formulaire(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }
}
