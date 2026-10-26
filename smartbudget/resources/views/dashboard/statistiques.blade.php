@extends('layouts.app')
@section('titre', 'Statistiques')

@section('entete')
    <div class="sb-page-head">
        <div>
            @php($de = preg_match('/^[aeiouéèêàâîôû]/iu', $periode['libelle']) ? "d'" : 'de ')
            <h1 class="sb-page-title">Statistiques {{ $de }}{{ $periode['libelle'] }}</h1>
            <p class="sb-page-sub">Où va votre argent, catégorie par catégorie.</p>
        </div>
    </div>
@endsection

@section('contenu')
    {{-- Sélecteur de période --}}
    <form method="GET" action="{{ route('statistiques') }}" class="card card-body mb-3 mb-xl-4" id="formulaire-periode">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-4 col-lg-3">
                <label for="periode" class="form-label">Période</label>
                <select id="periode" name="periode" class="form-select">
                    <option value="mois" @selected($periode['type'] === 'mois')>Un mois</option>
                    <option value="annee" @selected($periode['type'] === 'annee')>Une année</option>
                </select>
            </div>
            <div class="col-6 col-sm-4 col-lg-3" id="champ-mois">
                <label for="mois" class="form-label">Mois</label>
                <input type="month" id="mois" name="mois" value="{{ $periode['mois'] }}" class="form-control">
            </div>
            <div class="col-6 col-sm-4 col-lg-2" id="champ-annee">
                <label for="annee" class="form-label">Année</label>
                <input type="number" id="annee" name="annee" value="{{ $periode['annee'] }}" min="2000" max="2100" class="form-control">
            </div>
            <div class="col-12 col-lg-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-bar-chart me-1"></i>Afficher</button>
            </div>
        </div>

        @if ($erreurs->any())
            <div class="alert alert-warning mt-3 mb-0 py-2" role="alert">
                @foreach ($erreurs->all() as $erreur)
                    <div>{{ $erreur }}</div>
                @endforeach
                <div class="small">Le mois en cours est affiché à la place.</div>
            </div>
        @endif
    </form>

    <div class="row g-3 g-xl-4 mb-3 mb-xl-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card titre="Revenus" :valeur="format_fcfa($revenus)" icone="bi-arrow-up-right" variante="success" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card titre="Dépenses" :valeur="format_fcfa($depenses)" icone="bi-arrow-down-right" variante="danger" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card titre="Solde" :valeur="format_fcfa($solde)" icone="bi-wallet2"
                         :variante="$solde < 0 ? 'danger' : 'success'" :class="$solde < 0 ? 'sb-negative' : 'sb-positive'" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card titre="Moyenne des dépenses par mois" :valeur="format_fcfa($moyenneMensuelle)" icone="bi-calendar3" variante="primary" />
        </div>
    </div>

    @if ($plusCouteuse)
        <div class="sb-highlight mb-3 mb-xl-4" role="status">
            <span class="sb-highlight-icon"><i class="bi bi-trophy"></i></span>
            <span>
                Catégorie la plus coûteuse : <strong>{{ $plusCouteuse['categorie'] }}</strong>
                — <span class="sb-amount fw-bold">{{ format_fcfa($plusCouteuse['total']) }}</span>
                ({{ str_replace('.', ',', $plusCouteuse['pourcentage']) }} % des dépenses)
            </span>
        </div>
    @endif

    <div class="row g-3 g-xl-4 mb-3 mb-xl-4">
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <div class="sb-card-title">Dépenses par catégorie</div>
                    <div class="sb-card-sub">{{ $periode['libelle'] }}</div>
                </div>
                <div class="card-body">
                    @if ($repartition->isNotEmpty())
                        <div style="height: 300px"><canvas id="graphique-repartition" role="img" aria-label="Répartition des dépenses par catégorie"></canvas></div>
                    @else
                        <div class="sb-empty py-4"><i class="bi bi-pie-chart"></i>Aucune dépense sur cette période.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <div class="sb-card-title">Revenus et dépenses</div>
                    <div class="sb-card-sub">{{ $periode['type'] === 'annee' ? '12 mois de '.$periode['annee'] : '6 mois jusqu\'à '.$periode['libelle'] }}</div>
                </div>
                <div class="card-body">
                    <div style="height: 300px"><canvas id="graphique-mensuel" role="img" aria-label="Revenus et dépenses mois par mois"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tableau de répartition (montant et %) --}}
    <div class="card">
        <div class="card-header">
            <div class="sb-card-title">Répartition des dépenses par catégorie</div>
            <div class="sb-card-sub">Montant et part du total</div>
        </div>
        @if ($repartition->isEmpty())
            <div class="sb-empty py-4">Aucune dépense sur cette période.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 sb-table-compact">
                    <thead class="table-light">
                        <tr>
                            <th>Catégorie</th>
                            <th class="text-end">Montant</th>
                            <th>Part</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($repartition as $ligne)
                            <tr>
                                <td>
                                    <span class="sb-cat">
                                        <span class="sb-cat-dot sb-dep" aria-hidden="true">{{ mb_strtoupper(mb_substr($ligne['categorie'], 0, 1)) }}</span>
                                        <span class="sb-cat-name">{{ $ligne['categorie'] }}</span>
                                    </span>
                                </td>
                                <td class="text-end sb-amount fw-semibold">{{ format_fcfa($ligne['total']) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1 d-none d-sm-flex" style="height: 8px" role="progressbar" aria-valuenow="{{ $ligne['pourcentage'] }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar bg-danger" style="width: {{ $ligne['pourcentage'] }}%"></div>
                                        </div>
                                        <span class="small fw-semibold text-nowrap ms-auto" style="min-width: 52px; text-align: right">{{ str_replace('.', ',', $ligne['pourcentage']) }} %</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>Total</th>
                            <th class="text-end sb-amount">{{ format_fcfa($depenses) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script type="application/json" id="donnees-graphiques">@json($graphiques, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
    <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
    <script src="{{ asset('js/graphiques.js') }}"></script>
    <script>
        // N'affiche que le champ utile (mois ou année) selon la période choisie
        document.addEventListener('DOMContentLoaded', function () {
            var choix = document.getElementById('periode');
            function basculer() {
                var annee = choix.value === 'annee';
                document.getElementById('champ-mois').classList.toggle('d-none', annee);
                document.getElementById('champ-annee').classList.toggle('d-none', !annee);
            }
            choix.addEventListener('change', basculer);
            basculer();
        });
    </script>
@endpush
