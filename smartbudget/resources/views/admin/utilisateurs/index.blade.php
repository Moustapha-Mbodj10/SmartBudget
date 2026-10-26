@extends('layouts.app')
@section('titre', 'Utilisateurs')

@section('actions')
    <x-recherche :action="route('admin.utilisateurs.index')" :valeur="$recherche" libelle="Rechercher un utilisateur"
                 :suggestions="['nom', 'prénom', 'e-mail']" prefixe="Chercher par " />
@endsection

@section('contenu')
    <div class="card">
        @if ($utilisateurs->isEmpty())
            <div class="sb-empty">
                <i class="bi bi-search"></i>
                Aucun utilisateur trouvé.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sb-table-compact">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th class="d-none d-lg-table-cell sb-col-email">E-mail</th>
                            <th class="d-none d-sm-table-cell sb-col-secondaire">Rôle</th>
                            <th class="d-none d-sm-table-cell">Statut</th>
                            <th class="d-none d-md-table-cell">Connexion</th>
                            <th class="d-none d-lg-table-cell sb-col-secondaire">Inscrit le</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($utilisateurs as $u)
                            @php($soiMeme = $u->is(auth()->user()))
                            <tr>
                                <td class="fw-medium">
                                    {{ $u->prenom }} {{ $u->nom }}
                                    @if ($soiMeme) <span class="badge text-bg-light border ms-1">Vous</span> @endif
                                    <div class="d-lg-none sb-email-sous-nom small text-muted text-break">{{ $u->email }}</div>
                                    {{-- Sur téléphone, rôle et statut passent sous le nom pour libérer la largeur des boutons --}}
                                    <div class="d-sm-none mt-1">
                                        <span class="badge {{ $u->isAdmin() ? "text-bg-primary" : "text-bg-secondary" }}">{{ $u->isAdmin() ? "Administrateur" : "Utilisateur" }}</span>
                                        <span class="badge {{ $u->isActif() ? "text-bg-success" : "text-bg-danger" }}">{{ $u->isActif() ? "Actif" : "Suspendu" }}</span>
                                    </div>
                                    <div class="d-md-none mt-1 small"><x-connexion :utilisateur="$u" /></div>
                                </td>
                                <td class="d-none d-lg-table-cell sb-col-email text-muted">{{ $u->email }}</td>
                                <td class="d-none d-sm-table-cell sb-col-secondaire">
                                    @if ($u->isAdmin())
                                        <span class="badge text-bg-primary">Administrateur</span>
                                    @else
                                        <span class="badge text-bg-secondary">Utilisateur</span>
                                    @endif
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    @if ($u->isActif())
                                        <span class="badge text-bg-success">Actif</span>
                                    @else
                                        <span class="badge text-bg-danger">Suspendu</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell"><x-connexion :utilisateur="$u" /></td>
                                <td class="d-none d-lg-table-cell sb-col-secondaire text-nowrap">{{ format_date($u->created_at) }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.utilisateurs.show', $u) }}" class="btn btn-sm btn-outline-secondary" aria-label="Consulter {{ $u->prenom }} {{ $u->nom }}" title="Consulter"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('admin.utilisateurs.edit', $u) }}" class="btn btn-sm btn-outline-primary" aria-label="Modifier {{ $u->prenom }} {{ $u->nom }}" title="Modifier"><i class="bi bi-pencil"></i></a>

                                    @unless ($soiMeme || $u->isAdmin())
                                        @if ($u->isActif())
                                            <form method="POST" action="{{ route('admin.utilisateurs.suspendre', $u) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-warning" aria-label="Suspendre {{ $u->prenom }} {{ $u->nom }}" title="Suspendre"><i class="bi bi-pause-circle"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.utilisateurs.reactiver', $u) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-success" aria-label="Réactiver {{ $u->prenom }} {{ $u->nom }}" title="Réactiver"><i class="bi bi-play-circle"></i></button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.utilisateurs.destroy', $u) }}" class="d-inline"
                                              data-confirm="Supprimer définitivement le compte de {{ $u->prenom }} {{ $u->nom }} ? Toutes ses transactions et ses catégories seront supprimées.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer {{ $u->prenom }} {{ $u->nom }}" title="Supprimer"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">
                {{ $utilisateurs->total() }} compte{{ $utilisateurs->total() > 1 ? 's' : '' }}
            </div>
        @endif
    </div>

    <x-pagination :paginator="$utilisateurs" />
@endsection
