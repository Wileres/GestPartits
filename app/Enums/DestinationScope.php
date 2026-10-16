<?php

namespace App\Enums;

enum DestinationScope: string
{
    /** Grup d'un equip: un missatge per partit. */
    case Team = 'team';
    /** Canal o llista del club: un resum de la jornada. */
    case Club = 'club';

    public function label(): string
    {
        return match ($this) {
            self::Team => 'Equip',
            self::Club => 'Club',
        };
    }
}
