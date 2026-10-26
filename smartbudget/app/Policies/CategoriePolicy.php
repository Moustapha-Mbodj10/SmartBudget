<?php

namespace App\Policies;

use App\Models\Categorie;
use App\Models\User;

/** Un utilisateur ne modifie ou ne supprime que ses propres catégories (CDC §7.4). */
class CategoriePolicy
{
    public function update(User $user, Categorie $categorie): bool
    {
        return $user->id === $categorie->user_id;
    }

    public function delete(User $user, Categorie $categorie): bool
    {
        return $user->id === $categorie->user_id;
    }
}
