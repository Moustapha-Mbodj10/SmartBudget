@extends('layouts.guest')
@section('titre', 'Inscription')

@section('visuel')
    <a href="{{ url('/') }}" class="sb-brand">
        <span class="sb-logo"><x-logo /></span>SmartBudget
    </a>
    <div>
        <h2 class="sb-auth-claim">Votre argent, enfin lisible.</h2>
        <p class="sb-auth-lead">Créez votre compte en une minute. Dix catégories sont prêtes dès l'inscription : alimentation, transport, logement, salaire...</p>
    </div>
    <x-apercu-anime :erreur="$errors->any()" />
@endsection

@section('contenu')
    <h1>Créer un compte</h1>
    <p class="text-muted mb-4">Gratuit et sans engagement.</p>

    <form method="POST" action="{{ route('register') }}" novalidate data-connexion>
        @csrf
        <div class="row">
            <div class="col-12 col-sm-6 mb-3">
                <label for="prenom" class="form-label">Prénom</label>
                <input type="text" id="prenom" name="prenom" value="{{ old('prenom') }}" maxlength="100"
                       class="form-control @error('prenom') is-invalid @enderror" autocomplete="given-name" required autofocus>
                @error('prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 col-sm-6 mb-3">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" id="nom" name="nom" value="{{ old('nom') }}" maxlength="100"
                       class="form-control @error('nom') is-invalid @enderror" autocomplete="family-name" required>
                @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="150"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe <small class="text-muted fw-normal">(8 caractères minimum)</small></label>
            <div class="sb-pw">
                <input type="password" id="password" name="password" minlength="8" data-mdp
                       class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                <button type="button" class="sb-pw-toggle" data-afficher-mdp aria-pressed="false" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button>
            </div>
            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmer le mot de passe</label>
            <input type="password" id="password_confirmation" name="password_confirmation" data-mdp
                   class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">S'inscrire</button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">Déjà un compte ? <a href="{{ route('login') }}" class="fw-bold">Se connecter</a></p>
@endsection
