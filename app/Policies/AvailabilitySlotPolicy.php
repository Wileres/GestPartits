<?php

namespace App\Policies;

use App\Models\AvailabilitySlot;
use App\Models\User;

/**
 * Franges horàries: només l'administrador les modifica.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class AvailabilitySlotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AvailabilitySlot $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AvailabilitySlot $model): bool
    {
        return false;
    }

    public function delete(User $user, AvailabilitySlot $model): bool
    {
        return false;
    }
}
