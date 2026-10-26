<?php

namespace App\Http\Controllers;

use App\Services\BudgetStats;
use App\Support\FiltresTransactions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** EF8 tableau de bord, EF9 statistiques, EF10 graphiques (l'historique EF7 / la recherche EF11 viennent au Module 7). */
class DashboardController extends Controller
{
    /** Tableau de bord : situation du mois en cours. */
    public function index(Request $request): View
    {
        $stats = new BudgetStats($request->user());
        $debut = now()->startOfMonth();
        $fin = now()->endOfMonth();

        $revenus = $stats->totalRevenus($debut, $fin);
        $depenses = $stats->totalDepenses($debut, $fin);

        return view('dashboard.index', [
            'mois' => $debut->translatedFormat('F Y'),
            'revenus' => $revenus,
            'depenses' => $depenses,
            'solde' => $revenus - $depenses,
            'soldeCumule' => $stats->soldeCumule(),
            'nombreTransactions' => $stats->nombreTransactions($debut, $fin),
            'recentes' => $stats->dernieresTransactions(5),
            'aucuneDonnee' => ! $stats->aDesTransactions(),
            'graphiques' => [
                'repartition' => $stats->repartitionDepenses($debut, $fin)->values(),
                // Le dernier des 6 mois (le mois en cours) est mis en valeur dans le graphique
                'serie' => $stats->serieMensuelle(now(), 6) + ['surligne' => 5],
                // Courbe du solde cumulé à la fin de chaque mois (6 derniers mois)
                'evolution' => $stats->evolutionSolde(now(), 6),
            ],
        ]);
    }

    /** Statistiques et graphiques sur une période choisie : un mois ou une année. */
    public function statistiques(Request $request): View
    {
        $stats = new BudgetStats($request->user());
        [$periode, $erreurs] = $this->periode($request);

        $revenus = $stats->totalRevenus($periode['debut'], $periode['fin']);
        $depenses = $stats->totalDepenses($periode['debut'], $periode['fin']);
        $repartition = $stats->repartitionDepenses($periode['debut'], $periode['fin']);

        // Barres : 12 mois pour une année, sinon les 6 mois qui se terminent au mois choisi
        $serie = $periode['type'] === 'annee'
            ? $stats->serieMensuelle($periode['fin'], 12)
            : $stats->serieMensuelle($periode['fin'], 6);

        // Barre mise en valeur : le mois choisi (dernier des 6), ou le mois en cours quand on regarde l'année en cours
        $serie['surligne'] = $periode['type'] === 'annee'
            ? ($periode['annee'] === now()->year ? now()->month - 1 : null)
            : 5;

        return view('dashboard.statistiques', [
            'periode' => $periode,
            'erreurs' => $erreurs,
            'revenus' => $revenus,
            'depenses' => $depenses,
            'solde' => $revenus - $depenses,
            'repartition' => $repartition,
            'plusCouteuse' => $repartition->first(),
            'moyenneMensuelle' => $stats->moyenneMensuelleDepenses($periode['debut'], $periode['fin']),
            'graphiques' => ['repartition' => $repartition->values(), 'serie' => $serie],
        ]);
    }

    /** EF7 historique de toutes les transactions + EF11 recherche multicritère. */
    public function historique(Request $request): View|RedirectResponse
    {
        $stats = new BudgetStats($request->user());
        [$filtres, $erreurs] = FiltresTransactions::lire($request, avecType: true);

        $transactions = $stats->historique($filtres)->withQueryString();

        // Page demandée au-delà de la dernière (par exemple après avoir supprimé la dernière ligne de la dernière page) :
        // on renvoie vers la dernière page existante plutôt que d'afficher une page vide.
        if ($transactions->isEmpty() && $transactions->currentPage() > 1) {
            $request->session()->reflash(); // garde le message « supprimée avec succès » pour la page finale

            return redirect()->route('historique', array_merge($request->query(), ['page' => $transactions->lastPage()]));
        }

        $totaux = $stats->totauxFiltres($filtres);

        return view('dashboard.historique', [
            'transactions' => $transactions,
            'totaux' => $totaux,
            'solde' => $totaux['revenus'] - $totaux['depenses'],
            'filtres' => $filtres,
            'erreursFiltres' => $erreurs,
            'filtreActif' => $filtres !== [],
            'aDesTransactions' => $stats->aDesTransactions(),
            'categories' => $request->user()->categories()->orderBy('nom')->get()->groupBy('type'),
        ]);
    }

    /**
     * Lit la période demandée (?periode=mois&mois=2026-10 ou ?periode=annee&annee=2026).
     * Une valeur invalide est ignorée : on retombe sur le mois en cours, avec un message.
     *
     * @return array{0: array{type: string, mois: string, annee: int, debut: Carbon, fin: Carbon, libelle: string}, 1: \Illuminate\Support\MessageBag}
     */
    private function periode(Request $request): array
    {
        $validateur = Validator::make($request->query(), [
            'periode' => ['nullable', 'in:mois,annee'],
            'mois' => ['nullable', 'date_format:Y-m'],
            'annee' => ['nullable', 'integer', 'between:2000,2100'],
        ], [
            'periode.in' => 'La période doit être « mois » ou « année ».',
            'mois.date_format' => 'Le mois doit être au format AAAA-MM.',
            'annee.integer' => 'L\'année doit être un nombre entier.',
            'annee.between' => 'L\'année doit être comprise entre 2000 et 2100.',
        ]);
        $erreurs = $validateur->errors();

        $type = $request->query('periode') === 'annee' && ! $erreurs->has('periode') ? 'annee' : 'mois';
        $mois = $request->query('mois');
        $annee = $request->query('annee');

        $moisValide = $mois && ! $erreurs->has('mois');
        $anneeValide = $annee && ! $erreurs->has('annee');

        if ($type === 'annee') {
            $debut = Carbon::create($anneeValide ? (int) $annee : now()->year, 1, 1)->startOfDay();
            $fin = $debut->copy()->endOfYear();
            $libelle = 'l\'année '.$debut->year;
        } else {
            // « ! » remet les champs non lus à zéro : sans lui, le 31 du mois courant décalerait un mois court
            $debut = ($moisValide ? Carbon::createFromFormat('!Y-m', $mois) : now())->startOfMonth();
            $fin = $debut->copy()->endOfMonth();
            $libelle = $debut->translatedFormat('F Y');
        }

        return [[
            'type' => $type,
            'mois' => $debut->format('Y-m'),
            'annee' => $debut->year,
            'debut' => $debut,
            'fin' => $fin,
            'libelle' => $libelle,
        ], $erreurs];
    }
}
