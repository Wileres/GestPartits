<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

/**
 * Temporades: el gestor les gestiona.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Season $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Season $model): bool
    {
        return true;
    }

    public function delete(User $user, Season $model): bool
    {
        return true;
    }
}
