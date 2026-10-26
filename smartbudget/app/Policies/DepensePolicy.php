<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

/** Un utilisateur ne modifie ou ne supprime que ses propres dépenses (CDC §7.4). */
class DepensePolicy
{
    public function update(User $user, Depense $depense): bool
    {
        return $user->id === $depense->user_id;
    }

    public function delete(User $user, Depense $depense): bool
    {
        return $user->id === $depense->user_id;
    }
}
