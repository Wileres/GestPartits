<?php

namespace App\Events;

use App\Models\Schedule;
use App\Models\ScheduleVersion;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La programació d'una jornada s'ha confirmat (es poden generar imatges i avisos).
 */
class ScheduleConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Schedule $schedule, public readonly ScheduleVersion $version) {}
}
