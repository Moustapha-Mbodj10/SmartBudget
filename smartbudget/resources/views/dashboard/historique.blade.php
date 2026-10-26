@extends('layouts.app')
@section('titre', 'Historique')

@section('contenu')
    {{-- Recherche multicritère : tous les filtres se combinent --}}
    <form method="GET" action="{{ route('historique') }}" class="card card-body mb-3 mb-xl-4" role="search">
        <div class="row g-3">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Mot-clé</label>
                <input type="search" id="q" name="q" value="{{ $filtres['q'] ?? '' }}" maxlength="100" class="form-control" placeholder="Dans la description">
            </div>
            <div class="col-6 col-md-3">
                <label for="type" class="form-label">Type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Tous</option>
                    <option value="depense" @selected(($filtres['type'] ?? null) === 'depense')>Dépenses</option>
                    <option value="revenu" @selected(($filtres['type'] ?? null) === 'revenu')>Revenus</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label for="categorie_id" class="form-label">Catégorie</label>
                <select id="categorie_id" name="categorie_id" class="form-select">
                    <option value="">Toutes</option>
                    @foreach (['depense' => 'Dépenses', 'revenu' => 'Revenus'] as $type => $libelle)
                        @if (isset($categories[$type]))
                            <optgroup label="{{ $libelle }}">
                                @foreach ($categories[$type] as $categorie)
                                    <option value="{{ $categorie->id }}" @selected(($filtres['categorie_id'] ?? null) == $categorie->id)>{{ $categorie->nom }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="date_debut" class="form-label">Du</label>
                <input type="date" id="date_debut" name="date_debut" value="{{ $filtres['date_debut'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label for="date_fin" class="form-label">Au</label>
                <input type="date" id="date_fin" name="date_fin" value="{{ $filtres['date_fin'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="montant_min" class="form-label">Montant min.</label>
                <input type="number" id="montant_min" name="montant_min" value="{{ $filtres['montant_min'] ?? '' }}" min="0" step="0.01" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="montant_max" class="form-label">Montant max.</label>
                <input type="number" id="montant_max" name="montant_max" value="{{ $filtres['montant_max'] ?? '' }}" min="0" step="0.01" class="form-control">
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Rechercher</button>
                @if ($filtreActif)
                    <a href="{{ route('historique') }}" class="btn btn-outline-secondary">Réinitialiser</a>
                @endif
            </div>
        </div>

        @if ($erreursFiltres->any())
            <div class="alert alert-warning mt-3 mb-0 py-2" role="alert">
                @foreach ($erreursFiltres->all() as $erreur)
                    <div>{{ $erreur }}</div>
                @endforeach
            </div>
        @endif
    </form>

    {{-- Totaux des résultats (toutes les pages) --}}
    @if ($transactions->total() > 0)
        <div class="row g-3 g-xl-4 mb-3 mb-xl-4">
            <div class="col-12 col-sm-4">
                <x-stat-card :titre="'Revenus'.($filtreActif ? ' (résultats)' : '')" :valeur="format_fcfa($totaux['revenus'])" icone="bi-arrow-up-right" variante="success" />
            </div>
            <div class="col-12 col-sm-4">
                <x-stat-card :titre="'Dépenses'.($filtreActif ? ' (résultats)' : '')" :valeur="format_fcfa($totaux['depenses'])" icone="bi-arrow-down-right" variante="danger" />
            </div>
            <div class="col-12 col-sm-4">
                <x-stat-card :titre="'Solde'.($filtreActif ? ' (résultats)' : '')" :valeur="format_fcfa($solde)" icone="bi-wallet2"
                             :variante="$solde < 0 ? 'danger' : 'success'" :class="$solde < 0 ? 'sb-negative' : 'sb-positive'" />
            </div>
        </div>
    @endif

    <div class="card">
        @if ($transactions->isEmpty())
            <div class="sb-empty">
                @if ($filtreActif)
                    <i class="bi bi-search"></i>
                    Aucune transaction trouvée.
                @elseif (! $aDesTransactions)
                    <i class="bi bi-receipt"></i>
                    Aucune transaction enregistrée. Commencez par
                    <a href="{{ route('depenses.create') }}">ajouter une dépense</a> ou
                    <a href="{{ route('revenus.create') }}">un revenu</a>.
                @else
                    <i class="bi bi-receipt"></i>
                    Aucune transaction à afficher.
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sb-table-compact">
                    <thead class="table-light">
                        <tr>
                            <th class="d-none d-sm-table-cell">Date</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th class="d-none d-lg-table-cell sb-col-secondaire">Description</th>
                            <th class="text-end">Montant</th>
                            <th class="text-end"><span class="d-none d-sm-inline">Actions</span><span class="visually-hidden d-sm-none">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            @php($estRevenu = $t->type === 'revenu')
                            <tr>
                                <td class="text-nowrap d-none d-sm-table-cell">{{ format_date($t->date) }}</td>
                                <td>
                                    @if ($estRevenu)
                                        <span class="badge text-bg-success">Revenu</span>
                                    @else
                                        <span class="badge text-bg-danger">Dépense</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="sb-cat">
                                        <span class="sb-cat-dot {{ $estRevenu ? '' : 'sb-dep' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($t->categorie, 0, 1)) }}</span>
                                        <span class="sb-cat-name">{{ $t->categorie }}<div class="d-sm-none small text-muted">{{ format_date($t->date) }}</div></span>
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell sb-col-secondaire text-muted">{{ $t->description }}</td>
                                <td class="text-end fw-bold sb-amount {{ $estRevenu ? 'sb-positive' : 'sb-negative' }}">
                                    {{ $estRevenu ? '+' : '−' }} {{ format_fcfa($t->montant) }}
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex flex-column flex-sm-row gap-1">
                                        <a href="{{ route($estRevenu ? 'revenus.edit' : 'depenses.edit', $t->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Modifier" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route($estRevenu ? 'revenus.destroy' : 'depenses.destroy', $t->id) }}"
                                              data-confirm="Supprimer {{ $estRevenu ? 'ce revenu' : 'cette dépense' }} de {{ format_fcfa($t->montant) }} ?">
                                            @csrf
                                            @method('DELETE')
                                            {{-- Après suppression, on revient sur l'historique avec les mêmes filtres et la même page --}}
                                            <input type="hidden" name="retour" value="historique">
                                            <input type="hidden" name="retour_query" value="{{ request()->getQueryString() }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" title="Supprimer"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">
                {{ $transactions->total() }} transaction{{ $transactions->total() > 1 ? 's' : '' }}
            </div>
        @endif
    </div>

    <x-pagination :paginator="$transactions" />
@endsection
