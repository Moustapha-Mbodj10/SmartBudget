@extends('layouts.app')
@section('titre', 'Rechercher un utilisateur')

@section('entete')
    <div class="sb-page-head">
        <div>
            <h1 class="sb-page-title">Rechercher <span class="sb-soft">un utilisateur</span></h1>
            <p class="sb-page-sub">Par nom, prénom ou adresse e-mail.</p>
        </div>
    </div>
@endsection

@section('contenu')
    <form method="GET" action="{{ route('admin.recherche') }}" role="search" class="card mb-3 mb-xl-4">
        <div class="card-body">
            <label for="q" class="visually-hidden">Nom, prénom ou e-mail</label>
            <div class="sb-recherche-champ">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="q" name="q" value="{{ $terme }}" maxlength="150" autocomplete="off" autofocus
                       class="form-control form-control-lg" placeholder="Nom, prénom ou e-mail…">
                <button type="submit" class="btn btn-primary btn-lg">Rechercher</button>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-12 col-sm-6 col-md-4">
                    <label for="statut" class="form-label small text-muted mb-1">Statut du compte</label>
                    <select id="statut" name="statut" class="form-select">
                        <option value="">Tous</option>
                        <option value="actif" @selected($statut === 'actif')>Actifs</option>
                        <option value="suspendu" @selected($statut === 'suspendu')>Suspendus</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <label for="connexion" class="form-label small text-muted mb-1">Connexion</label>
                    <select id="connexion" name="connexion" class="form-select">
                        <option value="">Toutes</option>
                        <option value="en_ligne" @selected($connexion === 'en_ligne')>En ligne</option>
                        <option value="deconnecte" @selected($connexion === 'deconnecte')>Déconnectés</option>
                    </select>
                </div>
                @if ($recherche)
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <a href="{{ route('admin.recherche') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg me-1"></i>Effacer la recherche</a>
                    </div>
                @endif
            </div>
        </div>
    </form>

    @if (! $recherche)
        <div class="card">
            <div class="sb-empty">
                <i class="bi bi-person-search"></i>
                Tapez un nom, un prénom ou une adresse e-mail pour trouver un utilisateur.
            </div>
        </div>
    @elseif ($resultats->isEmpty())
        <div class="card" role="status">
            <div class="sb-empty">
                <i class="bi bi-search"></i>
                Aucun utilisateur ne correspond à votre recherche.
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header">
                <div class="sb-card-title">{{ $resultats->total() }} résultat{{ $resultats->total() > 1 ? 's' : '' }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sb-table-compact">
                    <thead class="table-light">
                        <tr>
                            <th>Utilisateur</th>
                            <th>Compte</th>
                            <th>Connexion</th>
                            <th class="text-end">Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resultats as $u)
                            <tr>
                                <td class="fw-medium">
                                    {{ surligner($u->prenom.' '.$u->nom, $terme) }}
                                    <div class="small text-muted text-break fw-normal">{{ surligner($u->email, $terme) }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $u->isAdmin() ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $u->isAdmin() ? 'Administrateur' : 'Utilisateur' }}</span>
                                    <span class="badge {{ $u->isActif() ? 'text-bg-success' : 'text-bg-danger' }}">{{ $u->isActif() ? 'Actif' : 'Suspendu' }}</span>
                                </td>
                                <td><x-connexion :utilisateur="$u" /></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.utilisateurs.show', $u) }}" class="btn btn-sm btn-outline-secondary" aria-label="Consulter {{ $u->prenom }} {{ $u->nom }}" title="Consulter"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('admin.utilisateurs.edit', $u) }}" class="btn btn-sm btn-outline-primary" aria-label="Modifier {{ $u->prenom }} {{ $u->nom }}" title="Modifier"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <x-pagination :paginator="$resultats" />
    @endif
@endsection
