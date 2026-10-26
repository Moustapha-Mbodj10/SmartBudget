<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erreur — SmartBudget</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/smartbudget.css') }}">
</head>
<body>
{{-- Page autonome : aucune session, aucun composant, aucun accès à la base (elle s'affiche aussi quand ceux-ci sont en panne) --}}
<div class="sb-guest">
    <div class="sb-guest-card">
        <div class="card">
            <div class="card-body">
                <div class="sb-error">
                    <div class="sb-error-code sb-bad">500</div>
                    <h1 class="h4 mt-2">Une erreur est survenue</h1>
                    <p class="text-muted">Un problème est survenu de notre côté. Vos données n'ont pas été perdues. Réessayez dans un instant.</p>
                    <a href="{{ url('/') }}" class="btn btn-primary">Retour à l'accueil</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
