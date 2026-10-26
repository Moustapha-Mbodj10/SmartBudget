@extends('layouts.guest')
@section('titre', 'Trop de tentatives')
@section('contenu')
    <div class="sb-error">
        <div class="sb-error-code sb-warn">429</div>
        <h2 class="h4 mt-2">Trop de tentatives</h2>
        <p class="text-muted">Trop de tentatives de connexion. Patientez une minute avant de réessayer.</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Retour à la connexion</a>
    </div>
@endsection
