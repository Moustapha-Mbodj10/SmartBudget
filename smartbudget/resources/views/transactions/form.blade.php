@extends('layouts.app')
@php($edition = $transaction !== null)
@section('titre', ($edition ? 'Modifier ' : 'Ajouter ').$cfg['article'].' '.$cfg['libelle'])

@section('actions')
    <a href="{{ route($cfg['route'].'.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour à la liste</a>
@endsection

@section('contenu')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="sb-stat-icon {{ $cfg['couleur'] === 'danger' ? 'sb-icon-dep' : 'sb-icon-rev' }}"><i class="bi {{ $cfg['icone'] }}"></i></span>
                        <div>
                            <div class="sb-card-title">{{ $edition ? 'Modifier' : $cfg['nouveau'] }} {{ $cfg['libelle'] }}</div>
                            <div class="sb-card-sub">{{ $cfg['couleur'] === 'danger' ? 'Une sortie d\'argent' : 'Une entrée d\'argent' }}</div>
                        </div>
                    </div>

                    @if ($categories->isEmpty())
                        <div class="alert alert-warning">
                            Vous n'avez encore aucune catégorie de {{ $cfg['libelle'] }}.
                            <a href="{{ route('categories.index') }}" class="alert-link">Créez-en une</a> avant de continuer.
                        </div>
                    @endif

                    <form method="POST" novalidate
                          action="{{ $edition ? route($cfg['route'].'.update', $transaction->id) : route($cfg['route'].'.store') }}">
                        @csrf
                        @if ($edition) @method('PUT') @endif

                        <div class="mb-4">
                            <label for="montant" class="form-label">Montant (FCFA)</label>
                            <input type="number" id="montant" name="montant" min="0.01" step="0.01" inputmode="decimal" required
                                   value="{{ old('montant', $edition ? $transaction->montant : '') }}" placeholder="0"
                                   class="form-control sb-amount-input @error('montant') is-invalid @enderror">
                            @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-12 col-sm-6 mb-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" id="date" name="date" max="{{ now()->format('Y-m-d') }}" required
                                       value="{{ old('date', $edition ? $transaction->date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                                       class="form-control @error('date') is-invalid @enderror">
                                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-sm-6 mb-3">
                                <label for="categorie_id" class="form-label">Catégorie</label>
                                <select id="categorie_id" name="categorie_id" required class="form-select @error('categorie_id') is-invalid @enderror">
                                    <option value="">Choisir une catégorie</option>
                                    @foreach ($categories as $categorie)
                                        <option value="{{ $categorie->id }}"
                                            @selected((string) old('categorie_id', $edition ? $transaction->categorie_id : '') === (string) $categorie->id)>
                                            {{ $categorie->nom }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('categorie_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description <small class="text-muted fw-normal">(facultative)</small></label>
                            <input type="text" id="description" name="description" maxlength="255"
                                   value="{{ old('description', $edition ? $transaction->description : '') }}"
                                   class="form-control @error('description') is-invalid @enderror">
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4" @disabled($categories->isEmpty())>Enregistrer</button>
                            <a href="{{ route($cfg['route'].'.index') }}" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
