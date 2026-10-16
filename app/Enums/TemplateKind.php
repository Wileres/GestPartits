<?php

namespace App\Enums;

enum TemplateKind: string
{
    /** Un missatge per partit (grups dels equips). */
    case Match = 'match';
    /** Resum de tota la jornada (canal del club). */
    case WeekendSummary = 'weekend_summary';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'Partit',
            self::WeekendSummary => 'Resum de la jornada',
        };
    }
}
