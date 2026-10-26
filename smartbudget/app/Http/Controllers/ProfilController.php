<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfilRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** EF3 — Gestion du profil : consulter, modifier ses informations, changer son mot de passe. */
class ProfilController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profil.edit', ['user' => $request->user()]);
    }

    /** Seuls nom et prénom sont modifiables : l'e-mail est définitif dès l'inscription, rôle et statut ne passent jamais par ce formulaire. */
    public function update(ProfilRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('profil.edit')->with('success', 'Vos informations ont été mises à jour.');
    }

    public function updatePassword(PasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]); // haché par le cast du modèle

        return redirect()->route('profil.edit')
            ->with('success', 'Votre mot de passe a été modifié.')
            ->with('onglet', 'password');
    }
}
