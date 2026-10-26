<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Middleware « actif » : déconnecte un utilisateur suspendu en cours de session (CDC §7.4). */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActif()) {
            DB::table('users')->where('id', $user->id)->update(['deconnecte_le' => now()]); // l'administrateur voit la déconnexion
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.suspended')]);
        }

        return $next($request);
    }
}
