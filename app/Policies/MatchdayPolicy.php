<?php

namespace App\Policies;

use App\Models\Matchday;
use App\Models\User;

/**
 * Jornades: el gestor les gestiona.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class MatchdayPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Matchday $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Matchday $model): bool
    {
        return true;
    }

    public function delete(User $user, Matchday $model): bool
    {
        return true;
    }
}
