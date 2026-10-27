<?php

namespace App\Support;

/**
 * Normalitza el nom d'un equip rival: cada paraula comença en majúscula, «de» i «del» van en minúscula,
 * «fue» va tot en majúscula i les lletres soltes (A, B, C, D…) també.
 */
final class TeamName
{
    private const LOWERCASE = ['de', 'del'];

    private const UPPERCASE = ['fue'];

    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return implode(' ', array_map(self::word(...), explode(' ', $name)));
    }

    private static function word(string $word): string
    {
        $lower = mb_strtolower($word);

        return match (true) {
            in_array($lower, self::LOWERCASE, true) => $lower,
            in_array($lower, self::UPPERCASE, true) => mb_strtoupper($word),
            mb_strlen($word) === 1 => mb_strtoupper($word),
            default => mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1),
        };
    }
}
