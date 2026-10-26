<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware « activite » : note le moment de la dernière requête d'une personne connectée, pour que l'administrateur
 * sache qui est en ligne. Écriture au plus une fois par minute et par personne (pas à chaque page), sans toucher
 * à « updated_at ».
 */
class SuivreActivite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->derniere_activite || $user->derniere_activite->lt(now()->subMinute()))) {
            DB::table('users')->where('id', $user->id)->update(['derniere_activite' => now(), 'deconnecte_le' => null]);
            $user->forceFill(['derniere_activite' => now(), 'deconnecte_le' => null])->syncOriginal();
        }

        return $next($request);
    }
}
