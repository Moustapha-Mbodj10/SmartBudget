@extends('layouts.guest')
@section('titre', 'Session expirée')
@section('contenu')
    <div class="sb-error">
        <div class="sb-error-code sb-warn">419</div>
        <h2 class="h4 mt-2">Session expirée</h2>
        <p class="text-muted">Pour votre sécurité, la page est restée ouverte trop longtemps. Rechargez-la puis recommencez.</p>
        <a href="{{ url('/') }}" class="btn btn-primary">Retour à l'accueil</a>
    </div>
@endsection
