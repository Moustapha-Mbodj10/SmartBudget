<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UtilisateursSupplementairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 22 comptes de démonstration supplémentaires, avec leurs revenus et leurs dépenses. */
class UtilisateursSupplementairesTest extends TestCase
{
    use RefreshDatabase;

    private function lancer(): void
    {
        $this->seed(UtilisateursSupplementairesSeeder::class);
    }

    /** Les comptes créés par le seeder (prenom.nom@smartbudget.sn), sans compter d'éventuels autres utilisateurs de la base de test. */
    private function crees()
    {
        return User::where('email', 'like', '%.%@smartbudget.sn')->get();
    }

    public function test_cree_22_comptes_utilisateurs_actifs_avec_des_adresses_distinctes(): void
    {
        $this->lancer();

        $comptes = $this->crees();
        $this->assertCount(22, $comptes);
        $this->assertCount(22, $comptes->pluck('email')->unique());
        $this->assertTrue($comptes->every(fn ($u) => $u->statut === User::STATUT_ACTIF));
        $this->assertTrue($comptes->every(fn ($u) => str_ends_with($u->email, '@smartbudget.sn')));
        $this->assertTrue($comptes->every(fn ($u) => $u->role === User::ROLE_UTILISATEUR), 'aucun administrateur créé');
    }

    public function test_les_comptes_peuvent_se_connecter_avec_le_mot_de_passe_de_demonstration(): void
    {
        $this->lancer();

        $this->post('/login', ['email' => 'fatou.sow@smartbudget.sn', 'password' => 'Password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('Password123', User::where('email', 'fatou.sow@smartbudget.sn')->value('password')));
    }

    public function test_chaque_compte_a_ses_13_categories_des_revenus_et_des_depenses(): void
    {
        $this->lancer();

        foreach ($this->crees() as $user) {
            $this->assertSame(13, $user->categories()->count(), $user->email);
            $this->assertGreaterThanOrEqual(6, $user->revenus()->count(), "revenus de {$user->email}");
            $this->assertGreaterThanOrEqual(60, $user->depenses()->count(), "dépenses de {$user->email}");
            $this->assertSame(0, $user->categories()->where('nom', 'Factures')->count(), "plus de catégorie « Factures »");
        }
    }

    public function test_les_transactions_sont_marquees_test_sans_date_future_et_avec_des_montants_realistes(): void
    {
        $this->lancer();

        $this->assertSame(0, Depense::where('description', 'not like', '[TEST]%')->count());
        $this->assertSame(0, Revenu::where('description', 'not like', '[TEST]%')->count());
        $this->assertSame(0, Depense::where('date', '>', Carbon::today())->count() + Revenu::where('date', '>', Carbon::today())->count());
        $this->assertSame(0, Depense::where('montant', '<=', 0)->count() + Revenu::where('montant', '<=', 0)->count());
        $this->assertLessThanOrEqual(1000000, (float) Revenu::max('montant'));
    }

    public function test_les_depenses_et_revenus_appartiennent_au_bon_compte_et_au_bon_type_de_categorie(): void
    {
        $this->lancer();

        foreach (Depense::with('categorie')->get() as $d) {
            $this->assertSame($d->user_id, $d->categorie->user_id);
            $this->assertSame(Categorie::TYPE_DEPENSE, $d->categorie->type);
        }
        foreach (Revenu::with('categorie')->get() as $r) {
            $this->assertSame($r->user_id, $r->categorie->user_id);
            $this->assertSame(Categorie::TYPE_REVENU, $r->categorie->type);
        }
    }

    public function test_les_donnees_sont_reproductibles_et_realistes_pour_le_solde(): void
    {
        $this->lancer();

        foreach ($this->crees() as $user) {
            $solde = (float) $user->revenus()->sum('montant') - (float) $user->depenses()->sum('montant');
            $this->assertGreaterThan(0, $solde, "le budget de {$user->email} reste positif");
        }
    }

    public function test_rejouable_sans_doublon_et_sans_toucher_aux_autres_donnees(): void
    {
        $this->seed(DatabaseSeeder::class); // awa, moussa, admin + leurs transactions
        $avantTotal = User::count();
        $avantAwa = User::where('email', 'awa@smartbudget.sn')->firstOrFail()->depenses()->count();

        $this->lancer();
        $depenses = Depense::count();
        $revenus = Revenu::count();
        $motDePasse = User::where('email', 'fatou.sow@smartbudget.sn')->value('password');

        $this->lancer();

        $this->assertCount(22, $this->crees());
        $this->assertSame($depenses, Depense::count());
        $this->assertSame($revenus, Revenu::count());
        $this->assertSame($motDePasse, User::where('email', 'fatou.sow@smartbudget.sn')->value('password'), 'un compte existant garde son mot de passe');
        $this->assertSame($avantAwa, User::where('email', 'awa@smartbudget.sn')->firstOrFail()->depenses()->count(), 'les comptes de démonstration existants ne sont pas touchés');
        $this->assertSame($avantTotal + 22, User::count());
    }

    public function test_le_seeder_par_defaut_ne_les_cree_pas(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertCount(0, $this->crees(), 'ils se créent à la demande, pas avec le seeder par défaut');
    }

    public function test_un_compte_cree_n_a_aucune_connexion_enregistree_et_l_administrateur_le_voit(): void
    {
        $avant = User::where('role', User::ROLE_UTILISATEUR)->count();
        $this->lancer();
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $reponse = $this->actingAs(User::where('email', 'admin@example.com')->first())->get('/admin');

        $this->assertSame($avant + 22, $reponse->viewData('total'));
        $this->assertTrue($this->crees()->every(fn ($u) => ! $u->estEnLigne() && $u->derniere_activite === null), "aucun des comptes créés n'a encore de connexion");
        $reponse->assertSee('aucune connexion enregistrée');
    }

    public function test_les_statistiques_d_un_compte_se_calculent_avec_les_nouvelles_donnees(): void
    {
        $this->lancer();
        $user = User::where('email', 'ibrahima.fall@smartbudget.sn')->firstOrFail();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('FCFA');
        $this->actingAs($user)->get('/statistiques')->assertOk()->assertSee('Facture globale')->assertDontSee("Facture d'eau");
    }

    public function test_adresses_sans_accent_ni_espace(): void
    {
        $this->assertSame('mariama.diallo@smartbudget.sn', UtilisateursSupplementairesSeeder::email('Mariama', 'Diallo'));
        $this->assertSame('seynabou.kane@smartbudget.sn', UtilisateursSupplementairesSeeder::email('Seynabou', 'Kane'));
    }
}
