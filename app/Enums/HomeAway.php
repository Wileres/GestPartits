<?php

namespace App\Enums;

enum HomeAway: string
{
    case Home = 'home';
    case Away = 'away';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Partit a casa',
            self::Away => 'Partit a fora',
        };
    }
}
