<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    use HasFactory;

    protected $table = 'depenses';

    /** `user_id` est fixé par le contrôleur à partir de l'utilisateur connecté. */
    protected $fillable = ['categorie_id', 'montant', 'date', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }
}
