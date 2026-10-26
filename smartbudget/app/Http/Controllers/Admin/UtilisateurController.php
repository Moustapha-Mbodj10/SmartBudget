<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UtilisateurRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestion des comptes par l'administrateur (CDC §3.7) : lister, rechercher, consulter, modifier,
 * suspendre / réactiver, supprimer. Routes protégées par les middlewares « auth », « actif » et « admin ».
 * Par confidentialité, l'administrateur ne voit jamais le détail des transactions : seulement leur nombre.
 */
class UtilisateurController extends Controller
{
    private const PAR_PAGE = 10;

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $motif = '%'.addcslashes($recherche, '%_\\').'%';

        $utilisateurs = User::query()
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', $motif)
                ->orWhere('prenom', 'like', $motif)
                ->orWhere('email', 'like', $motif)
                ->orWhereRaw("CONCAT(prenom, ' ', nom) like ?", [$motif])))
            ->orderBy('nom')->orderBy('prenom')->orderBy('id')
            ->paginate(self::PAR_PAGE)->withQueryString();

        return view('admin.utilisateurs.index', ['utilisateurs' => $utilisateurs, 'recherche' => $recherche]);
    }

    public function show(User $utilisateur): View
    {
        return view('admin.utilisateurs.show', [
            'utilisateur' => $utilisateur,
            // Uniquement le nombre : jamais les montants ni les descriptions
            'nombreTransactions' => $utilisateur->depenses()->count() + $utilisateur->revenus()->count(),
        ]);
    }

    public function edit(User $utilisateur): View
    {
        return view('admin.utilisateurs.edit', ['utilisateur' => $utilisateur]);
    }

    public function update(UtilisateurRequest $request, User $utilisateur): RedirectResponse
    {
        $utilisateur->update($request->validated());

        return redirect()->route('admin.utilisateurs.show', $utilisateur)->with('success', 'Utilisateur modifié avec succès.');
    }

    /**
     * Un compte suspendu ne peut plus se connecter. S'il est en ligne, il passe « déconnecté » tout de suite sur le tableau de bord
     * de l'administrateur, et sa session est coupée à sa prochaine requête (middleware « actif »), c'est-à-dire en moins de
     * quelques secondes même s'il ne clique sur rien : sa page vérifie régulièrement sa session (voir smartbudget.js).
     */
    public function suspendre(Request $request, User $utilisateur): RedirectResponse
    {
        if ($refus = $this->refuserSiCompteProtege($request, $utilisateur, 'suspendre')) {
            return $refus;
        }

        $etaitEnLigne = $utilisateur->estEnLigne();

        $utilisateur->statut = User::STATUT_SUSPENDU;
        $utilisateur->save();

        if ($etaitEnLigne) {
            // Hors ligne immédiatement ; une personne déjà déconnectée garde sa durée de déconnexion d'origine
            DB::table('users')->where('id', $utilisateur->id)->update(['deconnecte_le' => now()]);
        }

        return back()->with('success', 'Le compte de '.$utilisateur->prenom.' '.$utilisateur->nom.' est suspendu.');
    }

    public function reactiver(Request $request, User $utilisateur): RedirectResponse
    {
        $utilisateur->statut = User::STATUT_ACTIF;
        $utilisateur->save();

        return back()->with('success', 'Le compte de '.$utilisateur->prenom.' '.$utilisateur->nom.' est réactivé.');
    }

    /**
     * Supprime le compte avec ses transactions et ses catégories (CDC §3.7).
     * Les dépenses et revenus sont supprimés d'abord : la clé étrangère categorie_id est en RESTRICT, donc
     * supprimer directement l'utilisateur échouerait dès qu'il a des transactions. Le tout est atomique.
     */
    public function destroy(Request $request, User $utilisateur): RedirectResponse
    {
        if ($refus = $this->refuserSiCompteProtege($request, $utilisateur, 'supprimer')) {
            return $refus;
        }

        DB::transaction(function () use ($utilisateur) {
            $utilisateur->depenses()->delete();
            $utilisateur->revenus()->delete();
            $utilisateur->delete(); // ses catégories partent avec lui (ON DELETE CASCADE)
        });

        return redirect()->route('admin.utilisateurs.index')
            ->with('success', 'Le compte de '.$utilisateur->prenom.' '.$utilisateur->nom.' a été supprimé.');
    }

    /**
     * Comptes qu'on ne peut ni suspendre ni supprimer (CDC §3.7 et mémoire §2.2.2 : l'administrateur gère les
     * comptes utilisateurs) :
     *  - son propre compte (cahier des charges) ;
     *  - tout autre compte administrateur : sinon un compte administrateur compromis pourrait supprimer ou
     *    verrouiller tous les autres administrateurs.
     */
    private function refuserSiCompteProtege(Request $request, User $utilisateur, string $action): ?RedirectResponse
    {
        if ($utilisateur->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas '.$action.' votre propre compte.');
        }

        if ($utilisateur->isAdmin()) {
            return back()->with('error', 'Un compte administrateur ne peut être ni suspendu ni supprimé.');
        }

        return null;
    }
}
