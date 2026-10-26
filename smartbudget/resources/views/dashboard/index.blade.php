@extends('layouts.app')
@section('titre', 'Tableau de bord')

@section('entete')
    <div class="sb-page-head">
        <div>
            <h1 class="sb-page-title">Bon retour, <span class="sb-soft">{{ auth()->user()->prenom }}</span></h1>
            <p class="sb-page-sub">Voici où en sont vos finances ce mois-ci.</p>
        </div>
        <div class="sb-head-actions">
            <span class="sb-chip d-none d-sm-inline-flex"><i class="bi bi-calendar3"></i>{{ $mois }}</span>
            <a href="{{ route('depenses.create') }}" class="btn btn-danger"><i class="bi bi-plus-lg me-1"></i>Dépense</a>
            <a href="{{ route('revenus.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Revenu</a>
        </div>
    </div>
@endsection

@section('contenu')
    @if ($aucuneDonnee)
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle me-1"></i>
            Vous n'avez encore saisi aucune transaction. Commencez par
            <a href="{{ route('depenses.create') }}" class="alert-link">ajouter une dépense</a> ou
            <a href="{{ route('revenus.create') }}" class="alert-link">un revenu</a>.
        </div>
    @endif

    {{-- Ligne 1 : la carte verte du solde (le point fort de la page) et l'évolution sur 6 mois --}}
    <div class="row g-3 g-xl-4 mb-3 mb-xl-4">
        <div class="col-12 col-xl-4">
            <x-stat-card titre="Solde du mois" :valeur="format_fcfa($solde)" icone="bi-wallet2" :hero="true" :detail="'Solde cumulé : '.format_fcfa($soldeCumule)"
                         :variante="$solde < 0 ? 'danger' : 'success'" :class="$solde < 0 ? 'sb-negative' : 'sb-positive'" />
        </div>
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <div class="sb-card-title">Revenus et dépenses</div>
                    <div class="sb-card-sub">6 derniers mois</div>
                </div>
                <div class="card-body">
                    <div style="height: 260px"><canvas id="graphique-mensuel" role="img" aria-label="Revenus et dépenses des six derniers mois"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ligne 2 : les trois autres chiffres du mois (empilés) et la courbe d'évolution du solde --}}
    <div class="row g-3 g-xl-4 mb-3 mb-xl-4">
        <div class="col-12 col-xl-4">
            <div class="sb-stack">
                <x-stat-card titre="Revenus du mois" :valeur="format_fcfa($revenus)" icone="bi-arrow-up-right" variante="success" />
                <x-stat-card titre="Dépenses du mois" :valeur="format_fcfa($depenses)" icone="bi-arrow-down-right" variante="danger" />
                <x-stat-card titre="Transactions du mois" :valeur="$nombreTransactions" icone="bi-receipt" variante="primary" />
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <div class="sb-card-title">Évolution du solde</div>
                    <div class="sb-card-sub">Solde cumulé à la fin de chaque mois</div>
                </div>
                <div class="card-body">
                    <div style="height: 290px"><canvas id="graphique-solde" role="img" aria-label="Évolution du solde cumulé sur les six derniers mois"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ligne 3 : dernières transactions et répartition par catégorie --}}
    <div class="row g-3 g-xl-4">
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <div class="sb-card-title">Dernières transactions</div>
                        <div class="sb-card-sub">Revenus et dépenses confondus</div>
                    </div>
                    @if (Route::has('historique'))
                        <a href="{{ route('historique') }}" class="btn btn-sm btn-outline-secondary">Tout voir</a>
                    @endif
                </div>
                @if ($recentes->isEmpty())
                    <div class="sb-empty"><i class="bi bi-receipt"></i>Aucune transaction pour le moment.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 sb-table-compact">
                            <thead>
                                <tr>
                                    <th class="d-none d-sm-table-cell">Date</th>
                                    <th>Type</th>
                                    <th>Catégorie</th>
                                    <th class="d-none d-lg-table-cell sb-col-secondaire">Description</th>
                                    <th class="text-end">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentes as $t)
                                    <tr>
                                        <td class="text-nowrap d-none d-sm-table-cell">{{ format_date($t->date) }}</td>
                                        <td>
                                            @if ($t->type === 'revenu')
                                                <span class="badge text-bg-success">Revenu</span>
                                            @else
                                                <span class="badge text-bg-danger">Dépense</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="sb-cat">
                                                <span class="sb-cat-dot {{ $t->type === 'revenu' ? '' : 'sb-dep' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($t->categorie, 0, 1)) }}</span>
                                                <span class="sb-cat-name">{{ $t->categorie }}<div class="d-sm-none small text-muted">{{ format_date($t->date) }}</div></span>
                                            </span>
                                        </td>
                                        <td class="d-none d-lg-table-cell sb-col-secondaire text-muted">{{ $t->description }}</td>
                                        <td class="text-end fw-bold sb-amount {{ $t->type === 'revenu' ? 'sb-positive' : 'sb-negative' }}">
                                            {{ $t->type === 'revenu' ? '+' : '−' }} {{ format_fcfa($t->montant) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div class="sb-card-title">Dépenses par catégorie</div>
                    <div class="sb-card-sub">{{ $mois }}</div>
                </div>
                <div class="card-body">
                    @if (count($graphiques['repartition']))
                        <div style="height: 280px"><canvas id="graphique-repartition" role="img" aria-label="Répartition des dépenses du mois par catégorie"></canvas></div>
                    @else
                        <div class="sb-empty py-4"><i class="bi bi-pie-chart"></i>Aucune dépense ce mois-ci.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="application/json" id="donnees-graphiques">@json($graphiques, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
    <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
    <script src="{{ asset('js/graphiques.js') }}"></script>
@endpush
