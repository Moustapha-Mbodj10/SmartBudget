<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Module 0 — fondations : helpers de formatage, layout, composants, ressources locales. */
class FondationsTest extends TestCase
{
    public function test_format_fcfa_utilise_le_separateur_de_milliers(): void
    {
        $this->assertSame('150 000 FCFA', format_fcfa(150000));
        $this->assertSame('1 250 000 FCFA', format_fcfa('1250000.00'));
        $this->assertSame('0 FCFA', format_fcfa(0));
        $this->assertSame('0 FCFA', format_fcfa(null));
        $this->assertSame('1 500,50 FCFA', format_fcfa(1500.5));
    }

    public function test_format_date_est_au_format_jj_mm_aaaa(): void
    {
        $this->assertSame('12/10/2026', format_date('2026-10-12'));
        $this->assertSame('', format_date(null));
    }

    public function test_configuration_francaise_et_fuseau_de_dakar(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('Africa/Dakar', config('app.timezone'));
        $this->assertSame('SmartBudget', config('app.name'));
    }

    public function test_le_layout_invite_est_responsive_et_en_francais(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<html lang="fr">', false);
        $response->assertSee('name="viewport"', false);
        $response->assertSee('vendor/bootstrap/bootstrap.min.css', false);
    }

    public function test_le_layout_connecte_contient_menu_modale_et_composants(): void
    {
        $user = \App\Models\User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('id="confirmModal"', false);
        $response->assertSee('Awa Diop');
        $response->assertSee('action="'.route('logout').'"', false);
    }

    public function test_les_composants_blade_se_rendent(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-stat-card titre="Solde" :valeur="format_fcfa(175000)" variante="success" />'
        );

        $this->assertStringContainsString('175 000 FCFA', $html);
        $this->assertStringContainsString('sb-success', $html);
    }

    public function test_les_ressources_front_sont_servies_en_local(): void
    {
        foreach ([
            'vendor/bootstrap/bootstrap.min.css',
            'vendor/bootstrap/bootstrap.bundle.min.js',
            'vendor/bootstrap-icons/bootstrap-icons.min.css',
            'vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
            'vendor/chartjs/chart.umd.js',
            'css/smartbudget.css',
            'js/smartbudget.js',
        ] as $fichier) {
            $this->assertFileExists(public_path($fichier), $fichier);
        }
    }

    public function test_la_pagination_utilise_bootstrap_5(): void
    {
        $pages = new \Illuminate\Pagination\LengthAwarePaginator([], 30, 10, 1, ['path' => '/x']);

        $this->assertStringContainsString('page-link', $pages->links()->toHtml());
    }
}
