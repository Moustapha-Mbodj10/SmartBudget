@extends('layouts.app')
@section('titre', 'Mon profil')

@section('contenu')
    @php
        // Onglet actif décidé côté serveur : celui du formulaire qui vient d'échouer ou de réussir
        $ongletMdp = $errors->password->any() || session('onglet') === 'password';
    @endphp

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="sb-avatar sb-avatar-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->prenom, 0, 1).mb_substr($user->nom, 0, 1)) }}</span>
                    <div>
                        <div class="sb-card-title fs-4">{{ $user->prenom }} {{ $user->nom }}</div>
                        <div class="mt-1">
                            <span class="badge {{ $user->isAdmin() ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $user->isAdmin() ? 'Administrateur' : 'Utilisateur' }}</span>
                        </div>
                        <div class="sb-card-sub mt-1">Inscrit le {{ format_date($user->created_at) }}</div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 mt-xl-4">
                <div class="card-header pb-3">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button id="onglet-infos-tab" class="nav-link {{ $ongletMdp ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#onglet-infos"
                                    type="button" role="tab" aria-controls="onglet-infos" aria-selected="{{ $ongletMdp ? 'false' : 'true' }}">
                                <i class="bi bi-person me-1"></i>Informations
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button id="onglet-mdp-tab" class="nav-link {{ $ongletMdp ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#onglet-mdp"
                                    type="button" role="tab" aria-controls="onglet-mdp" aria-selected="{{ $ongletMdp ? 'true' : 'false' }}">
                                <i class="bi bi-key me-1"></i>Mot de passe
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body tab-content">
                    {{-- Onglet 1 : informations personnelles --}}
                    <div class="tab-pane fade {{ $ongletMdp ? '' : 'show active' }}" id="onglet-infos" role="tabpanel" aria-labelledby="onglet-infos-tab">
                        <form method="POST" action="{{ route('profil.update') }}" novalidate>
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" id="prenom" name="prenom" maxlength="100" required
                                           value="{{ old('prenom', $user->prenom) }}"
                                           class="form-control @error('prenom') is-invalid @enderror">
                                    @error('prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" id="nom" name="nom" maxlength="100" required
                                           value="{{ old('nom', $user->nom) }}"
                                           class="form-control @error('nom') is-invalid @enderror">
                                    @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                {{-- Lecture seule et sans attribut « name » : l'adresse n'est même pas envoyée avec le formulaire --}}
                                <input type="email" id="email" value="{{ $user->email }}" class="form-control" readonly aria-describedby="email-aide">
                                <div id="email-aide" class="form-text">Votre adresse e-mail est définitive : elle ne peut pas être modifiée après l'inscription.</div>
                            </div>

                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </form>
                    </div>

                    {{-- Onglet 2 : changement de mot de passe --}}
                    <div class="tab-pane fade {{ $ongletMdp ? 'show active' : '' }}" id="onglet-mdp" role="tabpanel" aria-labelledby="onglet-mdp-tab">
                        <form method="POST" action="{{ route('profil.password') }}" novalidate>
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Ancien mot de passe</label>
                                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required
                                       class="form-control @if ($errors->password->has('current_password')) is-invalid @endif">
                                @if ($errors->password->has('current_password'))
                                    <div class="invalid-feedback">{{ $errors->password->first('current_password') }}</div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label for="nouveau_mdp" class="form-label">Nouveau mot de passe <small class="text-muted">(8 caractères minimum)</small></label>
                                <input type="password" id="nouveau_mdp" name="password" minlength="8" autocomplete="new-password" required
                                       class="form-control @if ($errors->password->has('password')) is-invalid @endif">
                                @if ($errors->password->has('password'))
                                    <div class="invalid-feedback">{{ $errors->password->first('password') }}</div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required class="form-control">
                            </div>

                            <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
