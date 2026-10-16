<?php

namespace App\Policies;

use App\Models\FieldBlock;
use App\Models\User;

/**
 * Bloquejos del camp: només l'administrador els modifica.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class FieldBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FieldBlock $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, FieldBlock $model): bool
    {
        return false;
    }

    public function delete(User $user, FieldBlock $model): bool
    {
        return false;
    }
}
