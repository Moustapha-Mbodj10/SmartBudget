@extends('layouts.app')
@section('titre', $cfg['titre'])

@section('actions')
    <a href="{{ route($cfg['route'].'.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Ajouter {{ $cfg['article'] }} {{ $cfg['libelle'] }}
    </a>
@endsection

@section('contenu')
    {{-- Recherche et filtres combinables --}}
    <form method="GET" action="{{ route($cfg['route'].'.index') }}" class="card card-body mb-3 mb-xl-4" role="search">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Mot-clé</label>
                <input type="search" id="q" name="q" value="{{ $filtres['q'] ?? '' }}" maxlength="100"
                       class="form-control @error('q') is-invalid @enderror" placeholder="Dans la description">
            </div>
            <div class="col-12 col-md-4">
                <label for="categorie_id" class="form-label">Catégorie</label>
                <select id="categorie_id" name="categorie_id" class="form-select">
                    <option value="">Toutes</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}" @selected(($filtres['categorie_id'] ?? null) == $categorie->id)>{{ $categorie->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="date_debut" class="form-label">Du</label>
                <input type="date" id="date_debut" name="date_debut" value="{{ $filtres['date_debut'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
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
            <div class="col-12 col-md-8 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Rechercher</button>
                @if ($filtreActif)
                    <a href="{{ route($cfg['route'].'.index') }}" class="btn btn-outline-secondary">Réinitialiser</a>
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

    <div class="card">
        @if ($transactions->isEmpty())
            <div class="sb-empty">
                @if ($filtreActif)
                    <i class="bi bi-search"></i>
                    Aucune transaction trouvée.
                @else
                    <i class="bi {{ $cfg['icone'] }}"></i>
                    {{ $cfg['aucun'] }}. Commencez par saisir votre première transaction.
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sb-table-compact">
                    <thead class="table-light">
                        <tr>
                            <th class="d-none d-sm-table-cell">Date</th>
                            <th>Catégorie</th>
                            <th class="d-none d-lg-table-cell sb-col-secondaire">Description</th>
                            <th class="text-end">Montant</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="text-nowrap d-none d-sm-table-cell">{{ format_date($transaction->date) }}</td>
                                <td>
                                    <span class="sb-cat">
                                        <span class="sb-cat-dot {{ $cfg['couleur'] === 'danger' ? 'sb-dep' : '' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($transaction->categorie->nom, 0, 1)) }}</span>
                                        <span class="sb-cat-name">{{ $transaction->categorie->nom }}<div class="d-sm-none small text-muted">{{ format_date($transaction->date) }}</div></span>
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell sb-col-secondaire text-muted">{{ $transaction->description }}</td>
                                <td class="text-end fw-bold sb-amount text-{{ $cfg['couleur'] === 'danger' ? 'danger' : 'success' }}">
                                    {{ format_fcfa($transaction->montant) }}
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route($cfg['route'].'.edit', $transaction->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route($cfg['route'].'.destroy', $transaction->id) }}" class="d-inline"
                                          data-confirm="Supprimer {{ $cfg['ce'] }} {{ $cfg['libelle'] }} de {{ format_fcfa($transaction->montant) }} ?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="d-sm-none"></td>
                            <td colspan="2" class="d-none d-sm-table-cell"></td>
                            <td class="d-none d-lg-table-cell sb-col-secondaire"></td>
                            <td class="text-end fw-bold sb-amount" colspan="2">
                                {{ $filtreActif ? 'Total des résultats' : 'Total' }} : {{ format_fcfa($total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    <x-pagination :paginator="$transactions" />
@endsection
