<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

/**
 * Partits: el gestor els crea i els edita.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class GameMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GameMatch $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, GameMatch $model): bool
    {
        return true;
    }

    public function delete(User $user, GameMatch $model): bool
    {
        return true;
    }
}
