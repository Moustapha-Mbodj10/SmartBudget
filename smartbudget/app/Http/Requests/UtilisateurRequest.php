<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un compte par l'administrateur (CDC §3.7) : nom, prénom, e-mail uniquement.
 * Le rôle, le statut et le mot de passe ne passent jamais par ce formulaire.
 */
class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protégé par le middleware « admin » sur toutes les routes /admin
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->route('utilisateur')),
            ],
        ];
    }
}
