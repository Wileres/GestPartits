<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

/**
 * Programació: el gestor genera propostes, mou partits i confirma.
 */
class SchedulePolicy
{
    public function view(User $user, Schedule $schedule): bool
    {
        return true;
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return true;
    }
}
