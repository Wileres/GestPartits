<?php

namespace App\Events;

use App\Models\Matchday;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ha canviat algun partit o horari d'una jornada (els avisos ja enviats poden quedar desactualitzats).
 */
class ScheduleChanged
{
    use Dispatchable;

    public function __construct(public readonly Matchday $matchday) {}
}
