<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UtilisateursSupplementairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Commande de démonstration « demo:presence » : 12 comptes en ligne, les autres déconnectés depuis des durées variées. */
class PresenceDemoTest extends TestCase
{
    use RefreshDatabase;

    private function comptes()
    {
        $emails = UtilisateursSupplementairesSeeder::emails();

        return User::whereIn('email', $emails)->get()->sortBy(fn ($u) => array_search($u->email, $emails))->values();
    }

    private function preparer(): void
    {
        $this->seed(UtilisateursSupplementairesSeeder::class);
    }

    public function test_douze_comptes_sont_en_ligne_et_dix_sont_deconnectes(): void
    {
        $this->preparer();

        $this->artisan('demo:presence')->assertSuccessful();

        $comptes = $this->comptes();
        $this->assertCount(22, $comptes);
        $this->assertCount(12, $comptes->filter->estEnLigne());
        $this->assertCount(10, $comptes->reject->estEnLigne());
        $this->assertTrue($comptes->take(12)->every->estEnLigne(), 'les 12 premiers sont en ligne');
    }

    public function test_les_comptes_deconnectes_le_sont_depuis_des_durees_variees(): void
    {
        $this->preparer();
        $this->artisan('demo:presence')->assertSuccessful();

        $durees = $this->comptes()->slice(12)->map->dureeDeconnexion()->all();

        $this->assertCount(10, array_unique($durees), 'dix durées différentes');
        $attendues = ['45 minutes', '3 heures', '1 jour', '3 jours', '1 semaine', '2 semaines', '1 mois', '2 mois', '3 mois', '6 mois'];
        foreach ($attendues as $duree) {
            $this->assertNotEmpty(array_filter($durees, fn ($d) => str_starts_with($d, $duree)), "durée attendue : $duree (obtenu : ".implode(' | ', $durees).')');
        }
    }

    public function test_les_deconnexions_melangent_deconnexion_volontaire_et_session_expiree(): void
    {
        $this->preparer();
        $this->artisan('demo:presence')->assertSuccessful();

        $hors = $this->comptes()->slice(12);
        $this->assertGreaterThan(0, $hors->whereNotNull('deconnecte_le')->count());
        $this->assertGreaterThan(0, $hors->whereNull('deconnecte_le')->count());
        $this->assertTrue($hors->every(fn ($u) => $u->derniere_activite !== null && ! $u->estEnLigne()));
    }

    public function test_les_autres_comptes_ne_sont_jamais_touches(): void
    {
        $this->preparer();
        $vrai = User::factory()->create(['email' => 'vrai@gmail.com']);
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->artisan('demo:presence')->assertSuccessful();

        foreach ([$vrai, $admin] as $u) {
            $this->assertNull($u->fresh()->derniere_activite, $u->email);
            $this->assertNull($u->fresh()->deconnecte_le, $u->email);
        }
    }

    public function test_rejouable_la_presence_est_renouvelee(): void
    {
        $this->preparer();
        $this->artisan('demo:presence')->assertSuccessful();
        $premier = $this->comptes()->first();

        $this->travel(4)->minutes();
        $this->assertTrue($premier->fresh()->estEnLigne(), 'encore en ligne après 4 minutes');
        $this->travel(2)->minutes();
        $this->assertFalse($premier->fresh()->estEnLigne(), 'plus en ligne après 6 minutes sans activité');

        $this->artisan('demo:presence')->assertSuccessful();
        $this->assertCount(12, $this->comptes()->filter->estEnLigne());
    }

    public function test_sans_les_22_comptes_la_commande_explique_quoi_faire(): void
    {
        $this->artisan('demo:presence')->assertFailed()->expectsOutputToContain('UtilisateursSupplementairesSeeder');
    }

    public function test_refusee_en_production(): void
    {
        $this->preparer();
        $this->app['env'] = 'production';

        $this->artisan('demo:presence')->assertFailed()->expectsOutputToContain('refusée en production');
        $this->assertCount(0, $this->comptes()->filter->estEnLigne());
    }

    public function test_le_tableau_de_bord_de_l_administrateur_affiche_les_presences(): void
    {
        $this->preparer();
        $this->artisan('demo:presence')->assertSuccessful();
        $avant = User::where('role', User::ROLE_UTILISATEUR)->whereNotIn('email', UtilisateursSupplementairesSeeder::emails())->count();
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $reponse = $this->actingAs($admin)->get('/admin')->assertOk();

        // 12 comptes de démonstration en ligne (+ d'éventuels autres comptes de la base de test déjà en ligne)
        $autresEnLigne = User::where('role', User::ROLE_UTILISATEUR)->whereNotIn('email', UtilisateursSupplementairesSeeder::emails())->get()->filter->estEnLigne()->count();
        $this->assertSame(12 + $autresEnLigne, $reponse->viewData('enLigne'));
        $this->assertSame(22 + $avant, $reponse->viewData('total'));
        $reponse->assertSee('En ligne');
        // Les 12 en ligne sont listés en premier (page 1 : 10 personnes, toutes en ligne)
        $this->assertTrue(collect($reponse->viewData('utilisateurs')->items())->every->estEnLigne());
        $this->actingAs($admin)->get('/admin?page=2')->assertSee('depuis');
    }
}
