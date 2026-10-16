<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Dates en català: dies de la setmana, format DD/MM/YYYY i nom per defecte de les jornades.
 */
final class CatalanDate
{
    private const WEEKDAYS = [1 => 'dilluns', 2 => 'dimarts', 3 => 'dimecres', 4 => 'dijous', 5 => 'divendres', 6 => 'dissabte', 7 => 'diumenge'];

    public static function weekday(CarbonInterface $date): string
    {
        return self::WEEKDAYS[$date->isoWeekday()];
    }

    public static function weekdayName(int $isoWeekday): string
    {
        return self::WEEKDAYS[$isoWeekday];
    }

    /**
     * «17/10/2026».
     */
    public static function date(CarbonInterface $date): string
    {
        return $date->format('d/m/Y');
    }

    /**
     * Nom per defecte d'una jornada: «Jornada del 17/10/2026 al 18/10/2026» (o «Jornada del 17/10/2026» si és d'un sol dia).
     */
    public static function matchdayLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return 'Jornada del '.self::date($start);
        }

        return 'Jornada del '.self::date($start).' al '.self::date($end);
    }
}
