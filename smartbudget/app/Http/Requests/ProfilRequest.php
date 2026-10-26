<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification des informations personnelles (EF3) : nom et prénom seulement.
 * L'adresse e-mail est définitive dès l'inscription : elle n'est ni validée ni enregistrée ici, même si un champ « email » est envoyé.
 */
class ProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
        ];
    }
}
