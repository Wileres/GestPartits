<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppDestination;

/**
 * Configuració de WhatsApp: només l'administrador (Gate::before) la pot modificar; tothom la pot consultar.
 */
class WhatsAppDestinationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WhatsAppDestination $model): bool
    {
        return false;
    }

    public function delete(User $user, WhatsAppDestination $model): bool
    {
        return false;
    }
}
