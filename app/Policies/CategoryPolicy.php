<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Categories: només l'administrador les modifica; tothom les pot consultar.
 * (L'administrador té tots els permisos per Gate::before.)
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Category $model): bool
    {
        return false;
    }

    public function delete(User $user, Category $model): bool
    {
        return false;
    }
}
