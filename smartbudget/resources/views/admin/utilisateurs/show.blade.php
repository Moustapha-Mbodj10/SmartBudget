@extends('layouts.app')
@section('titre', $utilisateur->prenom.' '.$utilisateur->nom)

@section('contenu')
    @php($soiMeme = $utilisateur->is(auth()->user()))

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <a href="{{ route('admin.utilisateurs.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Retour à la liste</a>

            <div class="card mt-2">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-person-badge me-1"></i>{{ $utilisateur->prenom }} {{ $utilisateur->nom }}</span>
                    @if ($utilisateur->isActif())
                        <span class="badge text-bg-success">Actif</span>
                    @else
                        <span class="badge text-bg-danger">Suspendu</span>
                    @endif
                </div>

                <dl class="row mb-0 p-3">
                    <dt class="col-sm-5">Nom</dt><dd class="col-sm-7">{{ $utilisateur->nom }}</dd>
                    <dt class="col-sm-5">Prénom</dt><dd class="col-sm-7">{{ $utilisateur->prenom }}</dd>
                    <dt class="col-sm-5">E-mail</dt><dd class="col-sm-7 text-break">{{ $utilisateur->email }}</dd>
                    <dt class="col-sm-5">Rôle</dt><dd class="col-sm-7">{{ $utilisateur->isAdmin() ? 'Administrateur' : 'Utilisateur' }}</dd>
                    <dt class="col-sm-5">Connexion</dt><dd class="col-sm-7"><x-connexion :utilisateur="$utilisateur" /></dd>
                    <dt class="col-sm-5">Dernière activité</dt><dd class="col-sm-7">{{ $utilisateur->derniere_activite ? $utilisateur->derniere_activite->translatedFormat('d/m/Y à H:i') : 'Aucune connexion enregistrée' }}</dd>
                    <dt class="col-sm-5">Inscrit le</dt><dd class="col-sm-7">{{ format_date($utilisateur->created_at) }}</dd>
                    <dt class="col-sm-5">Nombre de transactions</dt><dd class="col-sm-7 mb-0">{{ $nombreTransactions }}</dd>
                </dl>

                <div class="card-footer bg-white text-muted small">
                    <i class="bi bi-shield-lock me-1"></i>Par confidentialité, le détail des transactions n'est pas accessible à l'administrateur.
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="{{ route('admin.utilisateurs.edit', $utilisateur) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>

                @unless ($soiMeme || $utilisateur->isAdmin())
                    @if ($utilisateur->isActif())
                        <form method="POST" action="{{ route('admin.utilisateurs.suspendre', $utilisateur) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-warning"><i class="bi bi-pause-circle me-1"></i>Suspendre</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.utilisateurs.reactiver', $utilisateur) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-success"><i class="bi bi-play-circle me-1"></i>Réactiver</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.utilisateurs.destroy', $utilisateur) }}"
                          data-confirm="Supprimer définitivement le compte de {{ $utilisateur->prenom }} {{ $utilisateur->nom }} ? Toutes ses transactions et ses catégories seront supprimées.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Supprimer</button>
                    </form>
                @endunless
            </div>

            @if ($soiMeme)
                <div class="form-text mt-2">Vous ne pouvez ni suspendre ni supprimer votre propre compte.</div>
            @elseif ($utilisateur->isAdmin())
                <div class="form-text mt-2">Un compte administrateur ne peut être ni suspendu ni supprimé.</div>
            @endif
        </div>
    </div>
@endsection
