<?php

namespace App\Enums;

enum WhatsAppProviderKey: string
{
    case Manual = 'manual';
    case Gateway = 'gateway';
    case Meta = 'meta';
    case Twilio = 'twilio';
    case Log = 'log';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual (copiar i enganxar)',
            self::Gateway => 'Passarel·la',
            self::Meta => 'WhatsApp Business (Meta)',
            self::Twilio => 'Twilio',
            self::Log => 'Simulació',
        };
    }
}
