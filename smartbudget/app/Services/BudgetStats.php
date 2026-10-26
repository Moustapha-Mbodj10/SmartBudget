<?php

namespace App\Services;

use App\Models\Categorie;
use App\Models\User;
use App\Support\FiltresTransactions;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calculs du tableau de bord et des statistiques (CDC §7.6).
 * Toutes les requêtes sont filtrées sur l'utilisateur donné et exécutées en SQL (SUM, GROUP BY) ;
 * les périodes sont des intervalles de dates [début, fin] bornes incluses, qui exploitent l'index (user_id, date).
 */
class BudgetStats
{
    public function __construct(private readonly User $user) {}

    public function totalDepenses(Carbon $debut, Carbon $fin): float
    {
        return (float) $this->user->depenses()->whereBetween('date', $this->bornes($debut, $fin))->sum('montant');
    }

    public function totalRevenus(Carbon $debut, Carbon $fin): float
    {
        return (float) $this->user->revenus()->whereBetween('date', $this->bornes($debut, $fin))->sum('montant');
    }

    /**
     * Solde cumulé à ce jour : tous les revenus moins toutes les dépenses depuis le début, jusqu'à aujourd'hui.
     * (Le solde du tableau de bord, lui, ne porte que sur le mois en cours.)
     */
    public function soldeCumule(): float
    {
        $aujourdhui = Carbon::today()->toDateString();

        return (float) $this->user->revenus()->where('date', '<=', $aujourdhui)->sum('montant')
            - (float) $this->user->depenses()->where('date', '<=', $aujourdhui)->sum('montant');
    }

    /** Nombre de transactions (revenus + dépenses) sur la période. */
    public function nombreTransactions(Carbon $debut, Carbon $fin): int
    {
        $bornes = $this->bornes($debut, $fin);

        return $this->user->depenses()->whereBetween('date', $bornes)->count()
            + $this->user->revenus()->whereBetween('date', $bornes)->count();
    }

    /**
     * Répartition des dépenses par catégorie, de la plus coûteuse à la moins coûteuse.
     *
     * @return Collection<int, array{categorie: string, total: float, pourcentage: float}>
     */
    public function repartitionDepenses(Carbon $debut, Carbon $fin): Collection
    {
        $lignes = $this->user->depenses()
            ->join('categories', 'categories.id', '=', 'depenses.categorie_id')
            ->whereBetween('depenses.date', $this->bornes($debut, $fin))
            ->selectRaw('categories.nom as categorie, SUM(depenses.montant) as total')
            ->groupBy('depenses.categorie_id', 'categories.nom')
            ->get()
            // « Facture globale » est l'addition de toutes les factures : les factures d'électricité, d'eau et d'internet
            // n'apparaissent pas à part dans la répartition, leur montant est compté dans « Facture globale ».
            ->groupBy(fn ($l) => in_array($l->categorie, Categorie::SOUS_FACTURES, true) ? Categorie::FACTURE_GLOBALE : $l->categorie)
            ->map(fn ($groupe, $nom) => (object) ['categorie' => $nom, 'total' => $groupe->sum(fn ($l) => (float) $l->total)])
            ->sortBy([['total', 'desc'], ['categorie', 'asc']])
            ->values();

        $somme = (float) $lignes->sum('total');

        return $lignes->map(fn ($l) => [
            'categorie' => $l->categorie,
            'total' => (float) $l->total,
            'pourcentage' => $somme > 0 ? round((float) $l->total / $somme * 100, 1) : 0.0,
        ]);
    }

    /**
     * Moyenne des dépenses par mois, calculée sur les mois de la période qui comportent au moins une dépense.
     */
    public function moyenneMensuelleDepenses(Carbon $debut, Carbon $fin): float
    {
        $requete = $this->user->depenses()->whereBetween('date', $this->bornes($debut, $fin));

        $mois = (int) (clone $requete)->selectRaw("COUNT(DISTINCT DATE_FORMAT(date, '%Y-%m')) as n")->value('n');

        return $mois > 0 ? (float) $requete->sum('montant') / $mois : 0.0;
    }

    /**
     * Revenus et dépenses mois par mois sur $nbMois mois, le dernier étant le mois de $dernierMois
     * (les mois sans transaction valent 0).
     *
     * @return array{labels: list<string>, revenus: list<float>, depenses: list<float>}
     */
    public function serieMensuelle(Carbon $dernierMois, int $nbMois): array
    {
        $fin = $dernierMois->copy()->endOfMonth();
        $debut = $dernierMois->copy()->startOfMonth()->subMonths($nbMois - 1);
        $bornes = $this->bornes($debut, $fin);

        $parMois = fn ($relation) => $relation->whereBetween('date', $bornes)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as mois, SUM(montant) as total")
            ->groupBy('mois')
            ->pluck('total', 'mois');

        $revenus = $parMois($this->user->revenus());
        $depenses = $parMois($this->user->depenses());

        $serie = ['labels' => [], 'revenus' => [], 'depenses' => []];
        for ($mois = $debut->copy(); $mois <= $fin; $mois->addMonth()) {
            $cle = $mois->format('Y-m');
            $serie['labels'][] = $mois->translatedFormat('M Y');
            $serie['revenus'][] = (float) ($revenus[$cle] ?? 0);
            $serie['depenses'][] = (float) ($depenses[$cle] ?? 0);
        }

        return $serie;
    }

    /**
     * Les dernières transactions (revenus et dépenses confondus), de la plus récente à la plus ancienne.
     *
     * @return Collection<int, object{type: string, date: Carbon, categorie: string, description: ?string, montant: float}>
     */
    public function dernieresTransactions(int $nombre = 5): Collection
    {
        $depuis = fn ($relation, string $type) => $relation->with('categorie')
            ->orderByDesc('date')->orderByDesc('id')->limit($nombre)->get()
            ->map(fn ($t) => (object) [
                'type' => $type,
                'date' => $t->date,
                'cree_le' => $t->created_at,
                'categorie' => $t->categorie->nom,
                'description' => $t->description,
                'montant' => (float) $t->montant,
            ]);

        return $depuis($this->user->depenses(), 'depense')
            ->concat($depuis($this->user->revenus(), 'revenu'))
            ->sortByDesc(fn ($t) => $t->date->format('Y-m-d').' '.$t->cree_le?->format('Y-m-d H:i:s'))
            ->take($nombre)
            ->values();
    }

    /**
     * Historique (EF7) : revenus et dépenses dans une seule liste, de la plus récente à la plus ancienne,
     * paginée en SQL (UNION des deux tables). Les filtres sont ceux de FiltresTransactions, `type` compris.
     * Chaque ligne : type ('depense' | 'revenu'), id, date, montant, description, created_at, categorie.
     *
     * @param  array<string, string>  $filtres
     */
    public function historique(array $filtres, int $parPage = 10): LengthAwarePaginator
    {
        $type = $filtres['type'] ?? null;

        $branches = [];
        if ($type !== 'revenu') {
            $branches[] = $this->brancheHistorique('depenses', 'depense', $filtres);
        }
        if ($type !== 'depense') {
            $branches[] = $this->brancheHistorique('revenus', 'revenu', $filtres);
        }

        $union = array_shift($branches);
        foreach ($branches as $branche) {
            $union->unionAll($branche);
        }

        // Ordre total (date, création, type, id) : la pagination reste stable d'une page à l'autre
        return DB::query()->fromSub($union, 't')
            ->orderByDesc('date')->orderByDesc('created_at')->orderBy('type')->orderByDesc('id')
            ->paginate($parPage);
    }

    /**
     * Totaux des revenus et des dépenses qui correspondent aux filtres (tous résultats, toutes pages).
     *
     * @param  array<string, string>  $filtres
     * @return array{revenus: float, depenses: float}
     */
    public function totauxFiltres(array $filtres): array
    {
        $type = $filtres['type'] ?? null;
        $somme = function (string $table) use ($filtres) {
            $requete = DB::table($table)->where("$table.user_id", $this->user->id);
            FiltresTransactions::appliquer($requete, $filtres, $table);

            return (float) $requete->sum("$table.montant");
        };

        return [
            'revenus' => $type === 'depense' ? 0.0 : $somme('revenus'),
            'depenses' => $type === 'revenu' ? 0.0 : $somme('depenses'),
        ];
    }

    /** @param  array<string, string>  $filtres */
    private function brancheHistorique(string $table, string $type, array $filtres): QueryBuilder
    {
        $requete = DB::table($table)
            ->join('categories', 'categories.id', '=', "$table.categorie_id")
            ->where("$table.user_id", $this->user->id)
            ->selectRaw("'$type' as type, $table.id, $table.date, $table.montant, $table.description, $table.created_at, categories.nom as categorie");

        FiltresTransactions::appliquer($requete, $filtres, $table);

        return $requete;
    }

    /**
     * Évolution du solde cumulé : la valeur à la fin de chacun des $nbMois derniers mois, le dernier étant le
     * mois de $dernierMois. Le point de départ est le solde accumulé avant le premier mois ; chaque mois ajoute
     * ses revenus et retire ses dépenses. Les transactions postérieures à aujourd'hui sont ignorées, comme dans
     * soldeCumule() : le dernier point du mois en cours égale donc le solde cumulé affiché sur le tableau de bord.
     *
     * @return array{labels: list<string>, valeurs: list<float>}
     */
    public function evolutionSolde(Carbon $dernierMois, int $nbMois): array
    {
        $aujourdhui = Carbon::today();
        $debut = $dernierMois->copy()->startOfMonth()->subMonths($nbMois - 1);
        $fin = $dernierMois->copy()->endOfMonth();
        $borne = $fin->lt($aujourdhui) ? $fin : $aujourdhui;

        // Solde accumulé avant le premier mois affiché (jamais au-delà d'aujourd'hui)
        $veille = $debut->copy()->subDay();
        $avant = ($veille->lt($aujourdhui) ? $veille : $aujourdhui)->toDateString();
        $solde = (float) $this->user->revenus()->where('date', '<=', $avant)->sum('montant')
            - (float) $this->user->depenses()->where('date', '<=', $avant)->sum('montant');

        $parMois = fn ($relation) => $relation
            ->whereBetween('date', [$debut->toDateString(), $borne->toDateString()])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as mois, SUM(montant) as total")
            ->groupBy('mois')
            ->pluck('total', 'mois');

        $revenus = $parMois($this->user->revenus());
        $depenses = $parMois($this->user->depenses());

        $evolution = ['labels' => [], 'valeurs' => []];
        for ($mois = $debut->copy(); $mois <= $fin; $mois->addMonth()) {
            $cle = $mois->format('Y-m');
            $solde += (float) ($revenus[$cle] ?? 0) - (float) ($depenses[$cle] ?? 0);
            $evolution['labels'][] = $mois->translatedFormat('M Y');
            $evolution['valeurs'][] = round($solde, 2);
        }

        return $evolution;
    }

    public function aDesTransactions(): bool
    {
        return $this->user->depenses()->exists() || $this->user->revenus()->exists();
    }

    /** @return array{0: string, 1: string} */
    private function bornes(Carbon $debut, Carbon $fin): array
    {
        return [$debut->toDateString(), $fin->toDateString()];
    }
}
