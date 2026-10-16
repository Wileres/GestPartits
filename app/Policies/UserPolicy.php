<?php

namespace App\Policies;

use App\Models\User;

/**
 * Només l'administrador gestiona usuaris (Gate::before li concedeix tots els permisos).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
