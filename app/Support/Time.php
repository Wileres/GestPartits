<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Hores locals expressades com a minuts des de mitjanit.
 */
final class Time
{
    public static function toMinutes(string $time): int
    {
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time, $m) !== 1) {
            throw new InvalidArgumentException("Hora no vàlida: {$time}");
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 'HH:MM:SS' (columna TIME) → 'HH:MM'.
     */
    public static function hm(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    public static function isOnGrid(string $time, int $stepMinutes): bool
    {
        return self::toMinutes($time) % $stepMinutes === 0;
    }
}
