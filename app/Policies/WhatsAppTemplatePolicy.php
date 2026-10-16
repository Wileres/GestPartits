<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppTemplate;

/**
 * Configuració de WhatsApp: només l'administrador (Gate::before) la pot modificar; tothom la pot consultar.
 */
class WhatsAppTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WhatsAppTemplate $model): bool
    {
        return false;
    }

    public function delete(User $user, WhatsAppTemplate $model): bool
    {
        return false;
    }
}
