@extends('layouts.app')
@section('titre', 'Tableau de bord')

@section('entete')
    <div class="sb-page-head">
        <div>
            <h1 class="sb-page-title">Bon retour, <span class="sb-soft">{{ auth()->user()->prenom }}</span></h1>
            <p class="sb-page-sub">Voici l'activité des comptes utilisateurs.</p>
        </div>
        <div class="sb-head-actions">
            <a href="{{ route('admin.utilisateurs.index') }}" class="btn btn-primary"><i class="bi bi-people me-1"></i>Gérer les utilisateurs</a>
        </div>
    </div>
@endsection

@section('contenu')
    <div data-actualiser="30000">
        {{-- Ligne 1 : le chiffre qui change le plus (les personnes en ligne) et les autres compteurs --}}
        <div class="row g-3 g-xl-4 mb-3 mb-xl-4">
            <div class="col-12 col-xl-4">
                <x-stat-card titre="En ligne maintenant" :valeur="$enLigne" icone="bi-broadcast" :hero="true"
                             :detail="$total === 0 ? 'Aucun compte utilisateur' : 'sur '.$total.' compte'.($total > 1 ? 's' : '').' utilisateur'.($total > 1 ? 's' : '')" variante="success" />
            </div>
            <div class="col-12 col-xl-8">
                <div class="row g-3 g-xl-4 h-100">
                    <div class="col-12 col-sm-6">
                        <x-stat-card titre="Comptes utilisateurs" :valeur="$total" icone="bi-people" variante="primary" />
                    </div>
                    <div class="col-12 col-sm-6">
                        <x-stat-card titre="Déconnectés" :valeur="$deconnectes" icone="bi-person-dash" variante="primary" />
                    </div>
                    <div class="col-12 col-sm-6">
                        <x-stat-card titre="Comptes actifs" :valeur="$actifs" icone="bi-person-check" variante="success" />
                    </div>
                    <div class="col-12 col-sm-6">
                        <x-stat-card titre="Comptes suspendus" :valeur="$suspendus" icone="bi-person-slash" variante="danger" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Ligne 2 : qui est en ligne, qui est déconnecté et depuis combien de temps --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="sb-card-title">Statut des utilisateurs</div>
                    <div class="sb-card-sub">En ligne : activité depuis moins de {{ \App\Models\User::DELAI_EN_LIGNE_MINUTES }} minutes. Mise à jour automatique toutes les 30 secondes.</div>
                </div>
                <span class="sb-chip"><i class="bi bi-person-plus"></i>{{ $nouveauxCeMois }} nouveau{{ $nouveauxCeMois > 1 ? 'x' : '' }} ce mois-ci</span>
            </div>

            @if ($utilisateurs->isEmpty())
                <div class="sb-empty"><i class="bi bi-people"></i>Aucun compte utilisateur pour le moment.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 sb-table-compact">
                        <thead class="table-light">
                            <tr>
                                <th>Utilisateur</th>
                                <th>Compte</th>
                                <th>Connexion</th>
                                <th class="d-none d-md-table-cell">Dernière activité</th>
                                <th class="text-end">Détail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($utilisateurs as $u)
                                <tr>
                                    <td class="fw-medium">
                                        {{ $u->prenom }} {{ $u->nom }}
                                        <div class="small text-muted text-break fw-normal">{{ $u->email }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $u->isActif() ? 'text-bg-success' : 'text-bg-danger' }}">{{ $u->isActif() ? 'Actif' : 'Suspendu' }}</span>
                                    </td>
                                    <td><x-connexion :utilisateur="$u" /></td>
                                    <td class="d-none d-md-table-cell text-muted text-nowrap">{{ $u->derniere_activite ? $u->derniere_activite->translatedFormat('d/m/Y à H:i') : '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.utilisateurs.show', $u) }}" class="btn btn-sm btn-outline-secondary" aria-label="Consulter {{ $u->prenom }} {{ $u->nom }}" title="Consulter"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">{{ $total }} compte{{ $total > 1 ? 's' : '' }} utilisateur{{ $total > 1 ? 's' : '' }}</div>
            @endif
        </div>

        <x-pagination :paginator="$utilisateurs" />
    </div>
@endsection
