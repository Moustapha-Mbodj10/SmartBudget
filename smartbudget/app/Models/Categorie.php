<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    use HasFactory;

    public const TYPE_DEPENSE = 'depense';

    public const TYPE_REVENU = 'revenu';

    /** Dans la répartition des dépenses, « Facture globale » regroupe les factures d'électricité, d'eau et d'internet. */
    public const FACTURE_GLOBALE = 'Facture globale';

    public const SOUS_FACTURES = ["Facture d'électricité", "Facture d'eau", "Facture d'internet"];

    /** Catégories proposées à l'inscription (CDC §3.3) : [nom, description, type]. */
    public const PAR_DEFAUT = [
        ['Alimentation', null, self::TYPE_DEPENSE],
        ['Transport', null, self::TYPE_DEPENSE],
        ['Logement', null, self::TYPE_DEPENSE],
        ['Santé', null, self::TYPE_DEPENSE],
        ['Éducation', null, self::TYPE_DEPENSE],
        ["Facture d'électricité", null, self::TYPE_DEPENSE],
        ["Facture d'eau", null, self::TYPE_DEPENSE],
        ["Facture d'internet", null, self::TYPE_DEPENSE],
        ['Facture globale', 'Eau, électricité et internet réunis', self::TYPE_DEPENSE],
        ['Loisirs', null, self::TYPE_DEPENSE],
        ['Salaire', null, self::TYPE_REVENU],
        ['Activité commerciale', null, self::TYPE_REVENU],
        ['Autres revenus', null, self::TYPE_REVENU],
    ];

    protected $table = 'categories';

    /** `user_id` est toujours fixé via la relation de l'utilisateur connecté, jamais par le formulaire. */
    protected $fillable = ['nom', 'description', 'type'];

    public function scopeDeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function revenus(): HasMany
    {
        return $this->hasMany(Revenu::class);
    }

    /** Nombre de transactions (dépenses + revenus) qui utilisent cette catégorie. */
    public function nombreTransactions(): int
    {
        return $this->depenses()->count() + $this->revenus()->count();
    }

    public function estUtilisee(): bool
    {
        return $this->depenses()->exists() || $this->revenus()->exists();
    }

    /** Crée les catégories par défaut d'un utilisateur (sans doublon si rejoué). */
    public static function creerParDefaut(User $user): void
    {
        foreach (self::PAR_DEFAUT as [$nom, $description, $type]) {
            $user->categories()->firstOrCreate(
                ['nom' => $nom, 'type' => $type],
                ['description' => $description],
            );
        }
    }
}
