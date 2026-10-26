<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Database\Seeders\UtilisateursSupplementairesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Présence de démonstration pour le tableau de bord de l'administrateur.
 *
 * Parmi les 22 comptes créés par UtilisateursSupplementairesSeeder : 12 sont mis « en ligne » et les 10 autres
 * « déconnectés » depuis des durées variées (de quelques heures à plusieurs mois). Les autres comptes (les vrais
 * comptes de la base, l'administrateur) ne sont jamais touchés.
 *
 * Une personne n'est « en ligne » que 5 minutes après sa dernière activité : avec --maintenir, la commande renouvelle
 * la présence des 12 comptes chaque minute jusqu'à Ctrl+C, pour garder l'écran de démonstration vivant.
 * DONNÉES DE TEST : n'écrit que `derniere_activite` et `deconnecte_le`. Refusée en production.
 */
class PresenceDemo extends Command
{
    protected $signature = 'demo:presence {--maintenir : renouvelle la présence des comptes en ligne chaque minute, jusqu\'à Ctrl+C}';

    protected $description = 'Met 12 comptes de démonstration en ligne et les autres hors ligne depuis des durées variées';

    public const NB_EN_LIGNE = 12;

    /**
     * Durées de déconnexion des comptes hors ligne : [unité, quantité, déconnexion volontaire ?].
     * Sans déconnexion volontaire, la session a simplement expiré (durée comptée depuis la dernière activité).
     * Les mois sont de vrais mois du calendrier : « 3 mois » s'affiche bien « 3 mois ».
     */
    public const DECONNEXIONS = [
        ['minutes', 45, true],
        ['hours', 3, false],
        ['days', 1, true],
        ['days', 3, false],
        ['weeks', 1, true],
        ['weeks', 2, false],
        ['months', 1, true],
        ['months', 2, false],
        ['months', 3, true],
        ['months', 6, false],
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Commande de démonstration : refusée en production.');

            return self::FAILURE;
        }

        $emails = UtilisateursSupplementairesSeeder::emails();
        if (DB::table('users')->whereIn('email', $emails)->count() < count($emails)) {
            $this->error('Les 22 comptes de démonstration n\'existent pas encore : php artisan db:seed --class=UtilisateursSupplementairesSeeder');

            return self::FAILURE;
        }

        $this->appliquer($emails);
        $this->afficher($emails);

        if ($this->option('maintenir')) {
            $this->info('Présence maintenue : les '.self::NB_EN_LIGNE.' comptes restent en ligne (Ctrl+C pour arrêter).');
            while (true) {
                sleep(60);
                $this->renouveler($emails);
            }
        }

        return self::SUCCESS;
    }

    /** Les 12 premiers comptes sont en ligne ; les suivants sont déconnectés depuis les durées de DECONNEXIONS. */
    private function appliquer(array $emails): void
    {
        $this->renouveler($emails);

        foreach (array_slice($emails, self::NB_EN_LIGNE) as $i => $email) {
            [$unite, $quantite, $volontaire] = self::DECONNEXIONS[$i % count(self::DECONNEXIONS)];
            $depuis = Carbon::now()->sub($unite, $quantite);

            DB::table('users')->where('email', $email)->update($volontaire
                ? ['derniere_activite' => $depuis->copy()->subMinutes(20), 'deconnecte_le' => $depuis]
                : ['derniere_activite' => $depuis, 'deconnecte_le' => null]);
        }
    }

    /** Remet les comptes « en ligne » à l'instant (avec quelques secondes d'écart pour que ce soit plausible). */
    private function renouveler(array $emails): void
    {
        foreach (array_slice($emails, 0, self::NB_EN_LIGNE) as $i => $email) {
            DB::table('users')->where('email', $email)
                ->update(['derniere_activite' => Carbon::now()->subSeconds(5 + $i * 17), 'deconnecte_le' => null]);
        }
    }

    private function afficher(array $emails): void
    {
        $this->info(self::NB_EN_LIGNE.' comptes en ligne, '.(count($emails) - self::NB_EN_LIGNE).' déconnectés :');
        foreach (\App\Models\User::whereIn('email', $emails)->get()->sortBy(fn ($u) => array_search($u->email, $emails)) as $u) {
            $this->line(sprintf('  %-24s %s', $u->prenom.' '.$u->nom, $u->estEnLigne() ? 'en ligne' : 'déconnecté depuis '.$u->dureeDeconnexion()));
        }
        $this->comment('« En ligne » = activité depuis moins de '.\App\Models\User::DELAI_EN_LIGNE_MINUTES.' minutes : relancez la commande (ou ajoutez --maintenir) pour renouveler.');
    }
}
