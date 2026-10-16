<?php

namespace App\Domain\Scheduling\Conflicts;

enum Severity: string
{
    /** Vermell: impedeix confirmar la programació. */
    case Critical = 'critical';
    /** Ambre: avís o preferència incomplerta. */
    case Warning = 'warning';
}
