<?php

use App\Http\Controllers\Admin\RechercheController;
use App\Http\Controllers\Admin\TableauDeBordController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RevenuController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route(Auth::user()->homeRoute())
        : redirect()->route('login');
});

// Invités uniquement (un utilisateur connecté est redirigé vers son espace)
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    // 10 inscriptions par minute et par adresse IP : limite la création de comptes en masse
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    // Limitation à 5 tentatives de connexion par minute (CDC §9.1)
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Espace connecté : un compte suspendu en cours de session est déconnecté (middleware « actif »)
Route::middleware(['auth', 'actif', 'activite'])->group(function () {
    // Battement de session : la page l'appelle toutes les 15 secondes. Une réponse 204 = session valide ; sinon (compte suspendu ou
    // supprimé, session expirée), la page renvoie tout de suite vers la connexion. Compte aussi comme activité (personne « en ligne »).
    Route::get('/session/etat', fn () => response()->noContent())->name('session.etat');

    // Profil : accessible à tout compte connecté (utilisateur ou administrateur)
    Route::get('/profil', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
    // 5 essais par minute : évite de deviner l'ancien mot de passe depuis une session ouverte
    Route::put('/profil/password', [ProfilController::class, 'updatePassword'])
        ->middleware('throttle:5,1')->name('profil.password');

    // Espace budget : réservé aux comptes « utilisateur » (l'administrateur reçoit 403)
    Route::middleware('utilisateur')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/statistiques', [DashboardController::class, 'statistiques'])->name('statistiques');
        Route::get('/historique', [DashboardController::class, 'historique'])->name('historique');

        // Le paramètre « transaction » est commun aux dépenses et aux revenus (contrôleur de base partagé)
        Route::resource('depenses', DepenseController::class)
            ->parameters(['depenses' => 'transaction'])
            ->except('show');

        Route::resource('revenus', RevenuController::class)
            ->parameters(['revenus' => 'transaction'])
            ->except('show');

        // Le paramètre doit s'appeler « categorie » (Laravel singularise « categories » en « category »)
        Route::resource('categories', CategorieController::class)
            ->parameters(['categories' => 'categorie'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    // Administration : réservée au rôle « admin » (403 sinon)
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Tableau de bord de l'administrateur : comptes, statuts, qui est en ligne et depuis combien de temps chacun est déconnecté
        Route::get('/', [TableauDeBordController::class, 'index'])->name('admin.dashboard');
        // Page dédiée à la recherche d'un utilisateur (nom, prénom, e-mail, statut, connexion)
        Route::get('/recherche', [RechercheController::class, 'index'])->name('admin.recherche');
        Route::patch('/utilisateurs/{utilisateur}/suspendre', [UtilisateurController::class, 'suspendre'])->name('admin.utilisateurs.suspendre');
        Route::patch('/utilisateurs/{utilisateur}/reactiver', [UtilisateurController::class, 'reactiver'])->name('admin.utilisateurs.reactiver');
        Route::resource('utilisateurs', UtilisateurController::class)
            ->parameters(['utilisateurs' => 'utilisateur'])
            ->only(['index', 'show', 'edit', 'update', 'destroy'])
            ->names('admin.utilisateurs');
    });
});
