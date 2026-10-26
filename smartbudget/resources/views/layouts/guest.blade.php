<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', 'Connexion') — SmartBudget</title>
    <link rel="preload" href="{{ asset('vendor/fonts/urbanist-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/smartbudget.css') }}">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
@hasSection('visuel')
    {{-- Pages de connexion et d'inscription : grande surface verte à gauche (ordinateur), formulaire à droite --}}
    <div class="sb-auth">
        <aside class="sb-auth-visual">
            @yield('visuel')
        </aside>
        <div class="sb-auth-form">
            <div class="sb-auth-box">
                <a href="{{ url('/') }}" class="sb-brand d-lg-none mb-4">
                    <span class="sb-logo"><x-logo /></span>SmartBudget
                </a>
                <x-alert-flash />
                @yield('contenu')
            </div>
        </div>
    </div>
@else
    {{-- Pages d'erreur : une carte centrée --}}
    <div class="sb-guest">
        <div class="sb-guest-card">
            <div class="text-center mb-4">
                <a href="{{ url('/') }}" class="sb-brand">
                    <span class="sb-logo"><x-logo /></span>SmartBudget
                </a>
            </div>
            <x-alert-flash />
            <div class="card">
                <div class="card-body">
                    @yield('contenu')
                </div>
            </div>
        </div>
    </div>
@endif
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/animations.js') }}"></script>
</body>
</html>
