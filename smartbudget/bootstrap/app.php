<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsUtilisateur;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuivreActivite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'actif' => EnsureUserIsActive::class,
            'activite' => SuivreActivite::class,
            'utilisateur' => EnsureUserIsUtilisateur::class,
        ]);

        // Global (et non seulement « web ») : les pages 404 d'URL inconnues passent aussi par ce middleware
        $middleware->append(SecurityHeaders::class);

        // Les contrôles de rôle passent AVANT la liaison des modèles de la route ({utilisateur}) :
        // sinon un utilisateur simple verrait 403 pour un compte existant et 404 pour un inexistant,
        // et pourrait ainsi deviner quels comptes existent.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureUserIsAdmin::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureUserIsUtilisateur::class);

        // Un utilisateur déjà connecté qui visite /login ou /register va sur son espace.
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
