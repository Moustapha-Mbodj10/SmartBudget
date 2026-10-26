<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page de l'administrateur dédiée à la recherche d'un utilisateur : par nom, prénom, nom complet ou adresse e-mail,
 * avec deux filtres facultatifs (statut du compte, en ligne / déconnecté). Aucun résultat tant qu'aucune recherche
 * n'est lancée. Par confidentialité, rien du budget des personnes n'est affiché.
 */
class RechercheController extends Controller
{
    private const PAR_PAGE = 10;

    public const STATUTS = ['actif', 'suspendu'];

    public const CONNEXIONS = ['en_ligne', 'deconnecte'];

    public function index(Request $request): View
    {
        $terme = trim((string) $request->query('q', ''));
        $statut = in_array($request->query('statut'), self::STATUTS, true) ? $request->query('statut') : '';
        $connexion = in_array($request->query('connexion'), self::CONNEXIONS, true) ? $request->query('connexion') : '';

        $recherche = $terme !== '' || $statut !== '' || $connexion !== '';
        $resultats = null;

        if ($recherche) {
            $motif = '%'.addcslashes($terme, '%_\\').'%';

            $resultats = User::query()
                ->when($terme !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('nom', 'like', $motif)
                    ->orWhere('prenom', 'like', $motif)
                    ->orWhere('email', 'like', $motif)
                    ->orWhereRaw("CONCAT(prenom, ' ', nom) like ?", [$motif])
                    ->orWhereRaw("CONCAT(nom, ' ', prenom) like ?", [$motif])))
                ->when($statut !== '', fn ($q) => $q->where('statut', $statut))
                ->when($connexion === 'en_ligne', fn ($q) => $q->enLigne())
                ->when($connexion === 'deconnecte', fn ($q) => $q->where(fn ($q) => $q
                    ->whereNotNull('deconnecte_le')
                    ->orWhereNull('derniere_activite')
                    ->orWhere('derniere_activite', '<', now()->subMinutes(User::DELAI_EN_LIGNE_MINUTES))))
                ->orderBy('nom')->orderBy('prenom')->orderBy('id')
                ->paginate(self::PAR_PAGE)->withQueryString();
        }

        return view('admin.recherche', compact('terme', 'statut', 'connexion', 'recherche', 'resultats'));
    }
}
