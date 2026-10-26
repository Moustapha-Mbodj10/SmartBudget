<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP (CDC §9.1) sur toutes les réponses web, redirections et erreurs comprises.
 * Pas de Content-Security-Policy : les pages utilisent de petits scripts en ligne (graphiques, modales).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Content-Type-Options', 'nosniff');          // pas de détection de type : un fichier n'est jamais exécuté comme script
        $reponse->headers->set('X-Frame-Options', 'SAMEORIGIN');              // pas d'affichage dans une iframe d'un autre site (clickjacking)
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Poste partagé (cybercafé) : après déconnexion, le bouton « retour » ne doit pas réafficher des données financières
        if ($request->user()) {
            $reponse->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $reponse;
    }
}
