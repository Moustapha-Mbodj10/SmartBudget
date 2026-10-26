<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Categorie;
use App\Support\FiltresTransactions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Logique commune aux dépenses (EF5) et aux revenus (EF4) : mêmes champs, mêmes règles,
 * seuls le modèle, le type de catégorie et les libellés changent (voir config()).
 */
abstract class TransactionController extends Controller
{
    private const PAR_PAGE = 10;

    /**
     * Clés : modele, relation (sur User), type de catégorie, route (préfixe), libelles...
     *
     * @return array<string, string>
     */
    abstract protected function config(): array;

    public function index(Request $request): View
    {
        $cfg = $this->config();

        [$filtres, $erreurs] = FiltresTransactions::lire($request);

        $requete = $request->user()->{$cfg['relation']}()->with('categorie');
        FiltresTransactions::appliquer($requete, $filtres, (new $cfg['modele'])->getTable());

        $total = (clone $requete)->sum('montant');
        $transactions = $requete->orderByDesc('date')->orderByDesc('id')
            ->paginate(self::PAR_PAGE)->withQueryString();

        return view($cfg['route'].'.index', [
            'cfg' => $cfg,
            'transactions' => $transactions,
            'categories' => $this->categories($request),
            'filtres' => $filtres,
            'erreursFiltres' => $erreurs,
            'filtreActif' => $filtres !== [],
            'total' => $total,
        ]);
    }

    public function create(Request $request): View
    {
        return view($this->config()['route'].'.create', [
            'cfg' => $this->config(),
            'transaction' => null,
            'categories' => $this->categories($request),
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $cfg = $this->config();

        $request->user()->{$cfg['relation']}()->create($request->validated());

        return redirect()->route($cfg['route'].'.index')->with('success', $cfg['msg_ajout']);
    }

    public function edit(Request $request, string $transaction): View
    {
        $modele = $this->trouver($transaction);
        Gate::authorize('update', $modele);

        return view($this->config()['route'].'.edit', [
            'cfg' => $this->config(),
            'transaction' => $modele,
            'categories' => $this->categories($request),
        ]);
    }

    public function update(TransactionRequest $request, string $transaction): RedirectResponse
    {
        $cfg = $this->config();

        $this->trouver($transaction)->update($request->validated());

        return redirect()->route($cfg['route'].'.index')->with('success', $cfg['msg_modif']);
    }

    public function destroy(Request $request, string $transaction): RedirectResponse
    {
        $cfg = $this->config();

        $modele = $this->trouver($transaction);
        Gate::authorize('delete', $modele);
        $modele->delete();

        // Suppression faite depuis l'historique : on y revient avec les mêmes filtres et la même page.
        // Seule la page « historique » est possible (aucune adresse libre : pas de redirection ouverte) et
        // seuls ses paramètres de recherche connus sont repris.
        if ($request->input('retour') === 'historique') {
            parse_str((string) $request->input('retour_query', ''), $parametres);
            $conserves = collect($parametres)
                ->only(['q', 'type', 'categorie_id', 'date_debut', 'date_fin', 'montant_min', 'montant_max', 'page'])
                ->filter(fn ($valeur) => is_string($valeur) && $valeur !== '')
                ->all();

            return redirect()->route('historique', $conserves)->with('success', $cfg['msg_suppr']);
        }

        return redirect()->route($cfg['route'].'.index')->with('success', $cfg['msg_suppr']);
    }

    private function trouver(string $id): Model
    {
        return $this->config()['modele']::findOrFail($id);
    }

    /** Catégories de l'utilisateur du bon type (dépense ou revenu). */
    private function categories(Request $request)
    {
        return $request->user()->categories()
            ->where('type', $this->config()['type'])
            ->orderBy('nom')
            ->get();
    }
}
