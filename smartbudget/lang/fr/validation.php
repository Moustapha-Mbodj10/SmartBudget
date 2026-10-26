<?php

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale à :date.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'decimal' => 'Le champ :attribute doit avoir au plus :decimal décimales.',
    'before_or_equal' => 'Le champ :attribute ne peut pas être dans le futur.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un entier.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'max' => [
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],

    'custom' => [
        'email' => [
            'unique' => 'Cet e-mail est déjà utilisé.',
        ],
    ],

    'attributes' => [
        'nom' => 'nom',
        'prenom' => 'prénom',
        'email' => 'e-mail',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'current_password' => 'ancien mot de passe',
        'montant' => 'montant',
        'date' => 'date',
        'categorie_id' => 'catégorie',
        'description' => 'description',
        'type' => 'type',
    ],
];
