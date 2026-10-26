@extends('layouts.guest')
@section('titre', 'Accès refusé')
@section('contenu')
    <div class="sb-error">
        <div class="sb-error-code sb-bad">403</div>
        <h2 class="h4 mt-2">Accès refusé</h2>
        <p class="text-muted">Vous n'avez pas le droit d'accéder à cette page.</p>
        <a href="{{ url('/') }}" class="btn btn-primary">Retour à l'accueil</a>
    </div>
@endsection
