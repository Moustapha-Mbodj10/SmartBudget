@extends('layouts.guest')
@section('titre', 'Page introuvable')
@section('contenu')
    <div class="sb-error">
        <div class="sb-error-code ">404</div>
        <h2 class="h4 mt-2">Page introuvable</h2>
        <p class="text-muted">La page demandée n'existe pas ou a été déplacée.</p>
        <a href="{{ url('/') }}" class="btn btn-primary">Retour à l'accueil</a>
    </div>
@endsection
