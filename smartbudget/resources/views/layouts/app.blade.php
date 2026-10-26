<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        {{-- Battement de session : voir smartbudget.js --}}
        <meta name="sb-session-etat" content="{{ route('session.etat') }}">
        <meta name="sb-session-login" content="{{ route('login') }}">
    @endauth
    <title>@yield('titre', 'Tableau de bord') — SmartBudget</title>
    <link rel="preload" href="{{ asset('vendor/fonts/urbanist-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/smartbudget.css') }}">
    <script>document.documentElement.classList.add('js');</script>
    <script>try { if (localStorage.getItem('sb-rail') === 'ouvert') { document.documentElement.classList.add('sb-rail-open'); } } catch (e) {}</script>
    @stack('styles')
</head>
<body>
@php
    $user = auth()->user();
    $estAdmin = $user && $user->role === 'admin';
    $initiales = $user ? mb_strtoupper(mb_substr($user->prenom, 0, 1).mb_substr($user->nom, 0, 1)) : '';

    // Navigation principale : les écrans de consultation (pastille du haut sur ordinateur)
    $menu = [
        ['route' => 'dashboard',      'actif' => 'dashboard',    'icone' => 'bi-grid-1x2',      'libelle' => 'Tableau de bord'],
        ['route' => 'depenses.index', 'actif' => 'depenses.*',   'icone' => 'bi-cash-stack',    'libelle' => 'Dépenses'],
        ['route' => 'revenus.index',  'actif' => 'revenus.*',    'icone' => 'bi-wallet2',       'libelle' => 'Revenus'],
        ['route' => 'statistiques',   'actif' => 'statistiques', 'icone' => 'bi-pie-chart',     'libelle' => 'Statistiques'],
        ['route' => 'historique',     'actif' => 'historique',   'icone' => 'bi-clock-history', 'libelle' => 'Historique'],
    ];
@endphp

<div class="sb-shell">
    {{-- Rail d'icônes (ordinateur) : ajouts rapides, catégories, profil, déconnexion --}}
    @auth
        <aside class="sb-rail" aria-label="Raccourcis">
            <div class="sb-rail-group">
                <button type="button" class="sb-rail-link sb-rail-toggle" data-rail-toggle aria-expanded="false" aria-label="Déplier le menu" title="Déplier le menu">
                    <i class="bi bi-layout-sidebar-inset"></i><span class="sb-rail-label">Réduire le menu</span>
                </button>
                <div class="sb-rail-sep"></div>
                @if ($estAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="sb-rail-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Tableau de bord" aria-label="Tableau de bord"><i class="bi bi-grid-1x2"></i><span class="sb-rail-label">Tableau de bord</span></a>
                    <a href="{{ route('admin.recherche') }}" class="sb-rail-link {{ request()->routeIs('admin.recherche') ? 'active' : '' }}" title="Rechercher un utilisateur" aria-label="Rechercher un utilisateur"><i class="bi bi-search"></i><span class="sb-rail-label">Rechercher</span></a>
                    @if (Route::has('admin.utilisateurs.index'))
                        <a href="{{ route('admin.utilisateurs.index') }}" class="sb-rail-link {{ request()->routeIs('admin.utilisateurs.*') ? 'active' : '' }}" title="Utilisateurs" aria-label="Utilisateurs"><i class="bi bi-people"></i><span class="sb-rail-label">Utilisateurs</span></a>
                    @endif
                @else
                    <a href="{{ route('dashboard') }}" class="sb-rail-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Tableau de bord" aria-label="Tableau de bord"><i class="bi bi-grid-1x2"></i><span class="sb-rail-label">Tableau de bord</span></a>
                    <div class="sb-rail-sep"></div>
                    <a href="{{ route('depenses.create') }}" class="sb-rail-link sb-add-depense" title="Ajouter une dépense" aria-label="Ajouter une dépense"><i class="bi bi-plus-lg"></i><span class="sb-rail-label">Ajouter une dépense</span></a>
                    <a href="{{ route('revenus.create') }}" class="sb-rail-link sb-add-revenu" title="Ajouter un revenu" aria-label="Ajouter un revenu"><i class="bi bi-plus-lg"></i><span class="sb-rail-label">Ajouter un revenu</span></a>
                    <div class="sb-rail-sep"></div>
                    <a href="{{ route('categories.index') }}" class="sb-rail-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" title="Catégories" aria-label="Catégories"><i class="bi bi-tags"></i><span class="sb-rail-label">Catégories</span></a>
                @endif
            </div>
            <div class="sb-rail-group">
                @if (Route::has('profil.edit'))
                    <a href="{{ route('profil.edit') }}" class="sb-rail-link {{ request()->routeIs('profil.*') ? 'active' : '' }}" title="Mon profil" aria-label="Mon profil"><i class="bi bi-person"></i><span class="sb-rail-label">Mon profil</span></a>
                @endif
                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sb-rail-link sb-logout" title="Déconnexion" aria-label="Déconnexion"><i class="bi bi-box-arrow-right"></i><span class="sb-rail-label">Déconnexion</span></button>
                    </form>
                @endif
            </div>
        </aside>
    @endauth

    <div class="sb-stage">
        <header class="sb-topbar">
            <a href="{{ auth()->check() ? route(auth()->user()->homeRoute()) : url('/') }}" class="sb-brand">
                <span class="sb-logo"><x-logo /></span><span class="sb-brand-name">SmartBudget</span>
            </a>

            {{-- Navigation : pastille centrale sur ordinateur, panneau latéral (burger) sur téléphone --}}
            <nav class="sb-nav offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Menu principal">
                <div class="offcanvas-header d-lg-none">
                    <span class="sb-nav-title">Menu</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Fermer"></button>
                </div>

                <ul class="sb-nav-list">
                    @if ($estAdmin)
                        <li><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid-1x2"></i>Tableau de bord</a></li>
                        <li><a class="nav-link {{ request()->routeIs('admin.recherche') ? 'active' : '' }}" href="{{ route('admin.recherche') }}"><i class="bi bi-search"></i>Rechercher</a></li>
                        @if (Route::has('admin.utilisateurs.index'))
                            <li><a class="nav-link {{ request()->routeIs('admin.utilisateurs.*') ? 'active' : '' }}" href="{{ route('admin.utilisateurs.index') }}"><i class="bi bi-people"></i>Utilisateurs</a></li>
                        @endif
                    @else
                        @foreach ($menu as $item)
                            @if (Route::has($item['route']))
                                <li>
                                    <a class="nav-link {{ request()->routeIs($item['actif']) ? 'active' : '' }}" href="{{ route($item['route']) }}"><i class="bi {{ $item['icone'] }}"></i>{{ $item['libelle'] }}</a>
                                </li>
                            @endif
                        @endforeach
                        {{-- Sur téléphone seulement : ce que le rail d'icônes porte sur ordinateur --}}
                        <li class="sb-nav-sep d-lg-none" role="separator"></li>
                        <li class="d-lg-none"><a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}"><i class="bi bi-tags"></i>Catégories</a></li>
                        <li class="d-lg-none"><a class="nav-link" href="{{ route('depenses.create') }}"><i class="bi bi-plus-circle"></i>Ajouter une dépense</a></li>
                        <li class="d-lg-none"><a class="nav-link" href="{{ route('revenus.create') }}"><i class="bi bi-plus-circle"></i>Ajouter un revenu</a></li>
                    @endif
                    @if (Route::has('profil.edit'))
                        <li class="d-lg-none"><a class="nav-link {{ request()->routeIs('profil.*') ? 'active' : '' }}" href="{{ route('profil.edit') }}"><i class="bi bi-person"></i>Mon profil</a></li>
                    @endif
                    @if (Route::has('logout'))
                        <li class="d-lg-none">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="nav-link"><i class="bi bi-box-arrow-right"></i>Déconnexion</button>
                            </form>
                        </li>
                    @endif
                </ul>
            </nav>

            <div class="sb-topbar-end">
                @auth
                    <a href="{{ route('profil.edit') }}" class="sb-user" title="Mon profil">
                        <span class="sb-avatar" aria-hidden="true">{{ $initiales }}</span>
                        <span>
                            <span class="sb-user-name d-block">{{ $user->prenom }} {{ $user->nom }}</span>
                            <span class="sb-user-role d-block">{{ $estAdmin ? 'Administrateur' : 'Utilisateur' }}</span>
                        </span>
                    </a>
                @endauth
                <button class="sb-burger d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Ouvrir le menu">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </header>

        <main>
            <x-alert-flash />

            @hasSection('entete')
                @yield('entete')
            @else
                <div class="sb-page-head">
                    <h1 class="sb-page-title">@yield('titre', 'Tableau de bord')</h1>
                    <div class="sb-head-actions">@yield('actions')</div>
                </div>
            @endif

            @yield('contenu')
        </main>
    </div>
</div>

<x-confirm-modal />

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/smartbudget.js') }}"></script>
<script src="{{ asset('js/animations.js') }}"></script>
@stack('scripts')
</body>
</html>
