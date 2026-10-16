<?php

namespace App\Enums;

enum RuleStrength: string
{
    case Mandatory = 'mandatory';
    case Recommended = 'recommended';

    public function label(): string
    {
        return match ($this) {
            self::Mandatory => 'Obligatòria',
            self::Recommended => 'Recomanada',
        };
    }
}
