<?php

namespace App\Enums;

enum TemplateAppliesTo: string
{
    case Home = 'home';
    case Away = 'away';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Partits a casa',
            self::Away => 'Partits a fora',
            self::Any => 'Tots els partits',
        };
    }
}
