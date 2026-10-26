@extends('layouts.guest')
@section('titre', 'Connexion')

@section('visuel')
    <a href="{{ url('/') }}" class="sb-brand">
        <span class="sb-logo"><x-logo /></span>SmartBudget
    </a>
    <div>
        <h2 class="sb-auth-claim">Gardez le cap sur votre budget.</h2>
        <p class="sb-auth-lead">Notez vos revenus et vos dépenses en FCFA, et voyez où va votre argent mois après mois.</p>
    </div>
    <x-apercu-anime :erreur="$errors->any()" />
@endsection

@section('contenu')
    <h1>Content de vous revoir</h1>
    <p class="text-muted mb-4">Connectez-vous pour retrouver votre budget.</p>

    <form method="POST" action="{{ route('login') }}" novalidate data-connexion>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="sb-pw">
                <input type="password" id="password" name="password" data-mdp
                       class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                <button type="button" class="sb-pw-toggle" data-afficher-mdp aria-pressed="false" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button>
            </div>
            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Se connecter</button>
    </form>

    <p class="text-center text-muted mt-4 mb-0">Pas encore de compte ? <a href="{{ route('register') }}" class="fw-bold">Créer un compte</a></p>
@endsection
