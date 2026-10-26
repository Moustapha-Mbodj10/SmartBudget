<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 9 — Garde-fous de structure pour T10 (affichage sur téléphone, 360 px).
 * Le rendu réel est mesuré dans un vrai navigateur par tests/Navigateur/verif_navigateur.cjs ;
 * ces tests empêchent seulement de réintroduire les défauts qu'il avait révélés.
 */
class ResponsiveTest extends TestCase
{
    use RefreshDatabase;

    private function vues(): array
    {
        $fichiers = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $fichiers[$f->getPathname()] = file_get_contents($f->getPathname());
            }
        }

        return $fichiers;
    }

    public function test_toutes_les_pages_declarent_le_viewport_mobile(): void
    {
        foreach (['layouts/app.blade.php', 'layouts/guest.blade.php', 'errors/500.blade.php'] as $vue) {
            $this->assertStringContainsString('name="viewport" content="width=device-width, initial-scale=1"', file_get_contents(resource_path("views/$vue")), $vue);
        }
    }

    public function test_chaque_tableau_est_dans_un_conteneur_responsive(): void
    {
        foreach ($this->vues() as $chemin => $contenu) {
            $this->assertSame(
                substr_count($contenu, '<table'),
                substr_count($contenu, 'class="table-responsive"'),
                basename($chemin).' : tout <table> doit être dans <div class="table-responsive">'
            );
        }
    }

    public function test_les_tableaux_de_transactions_utilisent_le_mode_compact_et_la_date_sous_la_categorie_sur_mobile(): void
    {
        foreach (['transactions/index', 'dashboard/historique', 'dashboard/index', 'admin/utilisateurs/index'] as $vue) {
            $contenu = file_get_contents(resource_path("views/$vue.blade.php"));
            $this->assertStringContainsString('sb-table-compact', $contenu, $vue);
        }
        foreach (['transactions/index', 'dashboard/historique', 'dashboard/index'] as $vue) {
            $contenu = file_get_contents(resource_path("views/$vue.blade.php"));
            $this->assertStringContainsString('d-none d-sm-table-cell">Date', $contenu, "$vue : la colonne Date est masquée sur téléphone");
            $this->assertStringContainsString('d-sm-none small text-muted', $contenu, "$vue : la date passe sous la catégorie sur téléphone");
        }
    }

    public function test_les_boutons_modifier_et_supprimer_de_l_historique_s_empilent_sur_telephone(): void
    {
        // À 360 px, deux boutons côte à côte faisaient défiler le tableau : ils s'empilent, puis se rangent en ligne dès « sm »
        $contenu = file_get_contents(resource_path('views/dashboard/historique.blade.php'));

        $this->assertStringContainsString('d-inline-flex flex-column flex-sm-row gap-1', $contenu);
        $this->assertStringContainsString('<span class="d-none d-sm-inline">Actions</span>', $contenu, 'titre de colonne masqué sur téléphone');
        $this->assertStringContainsString('visually-hidden', $contenu, 'mais conservé pour les lecteurs d\'écran');
    }

    public function test_la_barre_du_haut_et_la_navigation_s_adaptent_au_telephone(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/depenses/create')->getContent();

        $this->assertStringContainsString('sb-topbar', $html, 'barre du haut en pilule');
        $this->assertStringContainsString('offcanvas-lg offcanvas-start', $html, 'la navigation devient un panneau latéral sur téléphone');
        $this->assertStringContainsString('class="sb-burger d-lg-none"', $html, 'bouton burger visible seulement sur téléphone');
        $this->assertStringContainsString('class="sb-rail"', $html, 'rail des icônes, affiché seulement sur ordinateur par la feuille de style');
        $this->assertStringContainsString('sb-user', $html, 'pastille de l utilisateur connecté');
        // La déconnexion existe dans le rail (ordinateur) et dans le panneau (téléphone) : toujours un bouton accessible
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'aria-label="Déconnexion"'));
        $this->assertStringContainsString('<i class="bi bi-box-arrow-right"></i>Déconnexion', $html);
    }

    public function test_la_barre_laterale_a_un_bouton_pour_se_deplier_et_des_libelles(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();

        $this->assertStringContainsString('data-rail-toggle', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html, 'repliée par défaut');
        foreach (['Tableau de bord', 'Ajouter une dépense', 'Ajouter un revenu', 'Catégories', 'Mon profil', 'Déconnexion'] as $libelle) {
            $this->assertStringContainsString('<span class="sb-rail-label">'.$libelle.'</span>', $html, $libelle);
        }
        // L'état choisi est restauré avant l'affichage de la page (pas de saut visuel) et ne casse rien si le stockage est bloqué
        $this->assertStringContainsString("localStorage.getItem('sb-rail')", $html);
        $this->assertStringContainsString('catch (e) {}', $html);
    }

    public function test_la_barre_laterale_de_l_administrateur_ne_propose_que_son_espace(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/utilisateurs')->getContent();

        $this->assertStringContainsString('<span class="sb-rail-label">Utilisateurs</span>', $html);
        $this->assertStringContainsString('<span class="sb-rail-label">Mon profil</span>', $html);
        $this->assertStringNotContainsString('Ajouter une dépense', $html);
        $this->assertStringNotContainsString('sb-add-depense', $html);
    }

    public function test_la_barre_laterale_est_fixe_sur_toute_la_hauteur_de_l_ecran(): void
    {
        $css = file_get_contents(public_path('css/smartbudget.css'));

        // Sur ordinateur : position FIXE (et non « sticky »), collée en haut et en bas de l'écran
        $this->assertMatchesRegularExpression('~@media \(min-width: 992px\) \{\s*\.sb-shell[^}]*\}\s*\.sb-rail \{[^}]*position: fixed;[^}]*top: 1\.25rem; bottom: 1\.25rem;~s', $css);
        $this->assertDoesNotMatchRegularExpression('~\.sb-rail \{[^}]*position: sticky~', $css);
        // Largeur repliée / dépliée portée par une variable : le contenu se décale avec elle
        $this->assertStringContainsString('--sb-rail-w: 78px', $css);
        $this->assertStringContainsString('html.sb-rail-open { --sb-rail-w: 252px; }', $css);
        $this->assertStringContainsString('padding: 1.25rem 1.5rem 2rem calc(var(--sb-rail-w) + 2.5rem)', $css);
    }

    public function test_le_script_memorise_l_etat_de_la_barre_sans_planter_si_le_stockage_est_bloque(): void
    {
        $js = file_get_contents(public_path('js/smartbudget.js'));

        $this->assertStringContainsString("classList.toggle('sb-rail-open')", $js);
        $this->assertStringContainsString("localStorage.setItem('sb-rail'", $js);
        $this->assertMatchesRegularExpression('~try \{ localStorage\.setItem\([^}]*\} catch~', $js);
        $this->assertStringContainsString("setAttribute('aria-expanded'", $js);
    }

    public function test_la_feuille_de_style_garde_le_menu_lisible_et_le_rail_reserve_a_l_ordinateur(): void
    {
        $css = file_get_contents(public_path('css/smartbudget.css'));

        // Téléphone : panneau bleu foncé, liens clairs (jamais du texte clair sur fond clair)
        $this->assertMatchesRegularExpression('~\.sb-nav \{[^}]*background: linear-gradient\(180deg, var\(--sb-brand\) 0%, var\(--sb-brand-deep\)~', $css);
        // Ordinateur : le rail est bleu (dégradé du logo), liens blancs, lien actif en pilule blanche
        $this->assertMatchesRegularExpression('~\.sb-rail \{[^}]*background: linear-gradient\(180deg, var\(--sb-brand-bright\)~s', $css);
        $this->assertStringContainsString('.sb-rail-link.active { background: #fff; color: var(--sb-brand); }', $css);
        $this->assertMatchesRegularExpression('~\.sb-nav \.nav-link \{[^}]*color: #c9ced6~', $css);
        // Ordinateur : Bootstrap impose un fond transparent au menu (« .offcanvas-lg »), et c'est voulu : le fond vient de la pastille
        $this->assertMatchesRegularExpression('~@media \(min-width: 992px\) \{\s*\.sb-nav \{ background: transparent~', $css);
        $this->assertMatchesRegularExpression('~\.sb-rail \{ display: none; \}~', $css, 'le rail est masqué sous 992 px');
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_la_page_de_repartition_n_impose_pas_de_largeur_minimale_aux_colonnes(): void
    {
        $contenu = file_get_contents(resource_path('views/dashboard/statistiques.blade.php'));

        $this->assertStringNotContainsString('min-width: 160px', $contenu);
        $this->assertStringContainsString('d-none d-sm-flex', $contenu, 'barre de progression masquée sur téléphone');
    }
}
