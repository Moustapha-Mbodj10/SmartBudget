<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * Tableau de bord de l'administrateur : vue d'ensemble des comptes utilisateurs, de leur statut (actif / suspendu),
 * de leur présence (en ligne / déconnecté) et, pour chaque personne déconnectée, depuis combien de temps.
 * Par confidentialité, rien de ce qui concerne le budget des personnes (montants, descriptions) n'apparaît ici.
 */
class TableauDeBordController extends Controller
{
    private const PAR_PAGE = 10;

    public function index(): View
    {
        $comptes = User::where('role', User::ROLE_UTILISATEUR);

        $total = (clone $comptes)->count();
        $enLigne = (clone $comptes)->enLigne()->count();

        $utilisateurs = (clone $comptes)
            // En ligne d'abord, puis les déconnexions les plus récentes ; les comptes jamais connectés à la fin
            ->orderByRaw('(deconnecte_le IS NULL AND derniere_activite >= ?) DESC', [now()->subMinutes(User::DELAI_EN_LIGNE_MINUTES)])
            ->orderByRaw('COALESCE(deconnecte_le, derniere_activite) IS NULL')
            ->orderByRaw('COALESCE(deconnecte_le, derniere_activite) DESC')
            ->orderBy('nom')->orderBy('prenom')->orderBy('id')
            ->paginate(self::PAR_PAGE);

        return view('admin.dashboard', [
            'total' => $total,
            'enLigne' => $enLigne,
            'deconnectes' => $total - $enLigne,
            'actifs' => (clone $comptes)->where('statut', User::STATUT_ACTIF)->count(),
            'suspendus' => (clone $comptes)->where('statut', User::STATUT_SUSPENDU)->count(),
            'nouveauxCeMois' => (clone $comptes)->where('created_at', '>=', now()->startOfMonth())->count(),
            'utilisateurs' => $utilisateurs,
        ]);
    }
}
