<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Changement de mot de passe (CDC §7.5) : ancien mot de passe exigé, nouveau confirmé. */
class PasswordRequest extends FormRequest
{
    /** Les erreurs de ce formulaire sont séparées de celles du formulaire d'informations (même page, deux onglets). */
    protected $errorBag = 'password';

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
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
