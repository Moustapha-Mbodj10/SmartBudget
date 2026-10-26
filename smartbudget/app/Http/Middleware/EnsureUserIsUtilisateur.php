<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware « utilisateur » : réserve l'espace budget (catégories, transactions, tableau de bord)
 * aux comptes « utilisateur ». L'administrateur gère les comptes mais ne voit pas les
 * données financières (CDC §3.7, confidentialité) : il reçoit une erreur 403.
 */
class EnsureUserIsUtilisateur
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
