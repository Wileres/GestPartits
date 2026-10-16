<?php

namespace App\Enums;

enum GameFormat: string
{
    case Football7 = 'f7';
    case Football11 = 'f11';

    public function label(): string
    {
        return match ($this) {
            self::Football7 => 'Futbol 7',
            self::Football11 => 'Futbol 11',
        };
    }
}
