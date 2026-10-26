<?php

namespace App\Policies;

use App\Models\GeneratedImage;
use App\Models\User;

/**
 * Imatges generades: el gestor les consulta, les descarrega i les esborra.
 */
class GeneratedImagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GeneratedImage $image): bool
    {
        return true;
    }

    public function delete(User $user, GeneratedImage $image): bool
    {
        return true;
    }
}
