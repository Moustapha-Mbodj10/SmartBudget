<?php

namespace App\Policies;

use App\Models\Revenu;
use App\Models\User;

/** Un utilisateur ne modifie ou ne supprime que ses propres revenus (CDC §7.4). */
class RevenuPolicy
{
    public function update(User $user, Revenu $revenu): bool
    {
        return $user->id === $revenu->user_id;
    }

    public function delete(User $user, Revenu $revenu): bool
    {
        return $user->id === $revenu->user_id;
    }
}
