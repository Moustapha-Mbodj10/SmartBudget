<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_UTILISATEUR = 'utilisateur';

    public const ROLE_ADMIN = 'admin';

    public const STATUT_ACTIF = 'actif';

    public const STATUT_SUSPENDU = 'suspendu';

    /** Une personne est « en ligne » si elle a fait une requête depuis moins de ce nombre de minutes et ne s'est pas déconnectée. */
    public const DELAI_EN_LIGNE_MINUTES = 5;

    /**
     * `role` et `statut` ne sont volontairement pas assignables en masse :
     * un formulaire ne peut pas se donner le rôle administrateur.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'derniere_activite' => 'datetime',
            'deconnecte_le' => 'datetime',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Categorie::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function revenus(): HasMany
    {
        return $this->hasMany(Revenu::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActif(): bool
    {
        return $this->statut === self::STATUT_ACTIF;
    }

    /** Page d'accueil après connexion, selon le rôle (CDC §3.1). */
    public function homeRoute(): string
    {
        return $this->isAdmin() ? 'admin.dashboard' : 'dashboard';
    }

    /** En ligne : activité récente et aucune déconnexion depuis. */
    public function estEnLigne(): bool
    {
        return $this->deconnecte_le === null
            && $this->derniere_activite !== null
            && $this->derniere_activite->gte(now()->subMinutes(self::DELAI_EN_LIGNE_MINUTES));
    }

    /** Moment à partir duquel la personne est déconnectée (null si elle est en ligne ou ne s'est jamais connectée). */
    public function deconnecteDepuis(): ?Carbon
    {
        return $this->estEnLigne() ? null : ($this->deconnecte_le ?? $this->derniere_activite);
    }

    /** Durée de déconnexion en toutes lettres : « 2 heures et 15 minutes ». Null si en ligne ou jamais connectée. */
    public function dureeDeconnexion(): ?string
    {
        $depuis = $this->deconnecteDepuis();

        if ($depuis && $depuis->diffInSeconds(now()) < 60) {
            return "moins d'une minute";
        }

        return $depuis?->diffForHumans(now(), [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'join' => ' et ', 'minimumUnit' => 'minute', // jamais de secondes
        ]);
    }

    public function scopeEnLigne(Builder $query): Builder
    {
        return $query->whereNull('deconnecte_le')->where('derniere_activite', '>=', now()->subMinutes(self::DELAI_EN_LIGNE_MINUTES));
    }
}
