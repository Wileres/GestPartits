<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * Equips: el gestor els pot crear, editar, ordenar i desactivar.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $model): bool
    {
        return true;
    }

    public function delete(User $user, Team $model): bool
    {
        return true;
    }
}
