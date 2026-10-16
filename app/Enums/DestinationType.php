<?php

namespace App\Enums;

enum DestinationType: string
{
    case Group = 'group';
    case Channel = 'channel';
    case Phone = 'phone';
    case List = 'list';

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Grup',
            self::Channel => 'Canal',
            self::Phone => 'Telèfon',
            self::List => 'Llista de difusió',
        };
    }
}
