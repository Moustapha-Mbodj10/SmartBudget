<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Categorie;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /** EF1 — Inscription : rôle « utilisateur » et statut « actif » par défaut (valeurs de la BDD). */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->validated());
            Categorie::creerParDefaut($user); // catégories proposées à l'inscription (CDC §3.3)

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $this->noterConnexion($user);

        return redirect()->route($user->homeRoute())
            ->with('success', 'Bienvenue sur SmartBudget, '.$user->prenom.' ! Votre compte a été créé.');
    }

    /** Suivi de connexion de l'administrateur : la personne est en ligne à partir de maintenant. */
    private function noterConnexion(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['derniere_activite' => now(), 'deconnecte_le' => null]);
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * EF2 — Connexion. Message précis si les identifiants sont faux (« Mot de passe incorrect. » ou adresse inconnue,
     * à la demande de l'utilisateur) ; accès refusé si le compte est suspendu ; redirection selon le rôle.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $identifiants = $request->only('email', 'password');

        if (! Auth::attempt($identifiants)) {
            // Message précis : mot de passe faux sur le champ du mot de passe, adresse inconnue sur le champ e-mail.
            $erreur = User::where('email', $identifiants['email'])->exists()
                ? ['password' => __('auth.password')]
                : ['email' => __('auth.inconnu')];

            return redirect()->route('login')->withInput($request->only('email'))->withErrors($erreur);
        }

        $user = Auth::user();

        if (! $user->isActif()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.suspended')]);
        }

        $request->session()->regenerate();
        $this->noterConnexion($user);

        return redirect()->intended(route($user->homeRoute()));
    }

    /** EF12 — Déconnexion : session invalidée, retour à la page de connexion. */
    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            DB::table('users')->where('id', $request->user()->id)->update(['deconnecte_le' => now()]); // pour le suivi de l'administrateur
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Vous avez été déconnecté.');
    }
}
