<?php

namespace App\Policies;

use App\Models\SchedulingRule;
use App\Models\User;

/**
 * Regles de programació: només l'administrador les modifica.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class SchedulingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SchedulingRule $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SchedulingRule $model): bool
    {
        return false;
    }

    public function delete(User $user, SchedulingRule $model): bool
    {
        return false;
    }
}
