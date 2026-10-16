<?php

namespace App\Domain\Scheduling\Model;

use InvalidArgumentException;

/**
 * Conversió d'hores locals «HH:MM» a minuts des de mitjanit (el domini treballa sempre en minuts).
 */
final class Minutes
{
    public static function parse(string $time): int
    {
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time, $m) !== 1) {
            throw new InvalidArgumentException("Hora no vàlida: {$time}");
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    public static function parseOrNull(?string $time): ?int
    {
        return $time === null || $time === '' ? null : self::parse($time);
    }

    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
