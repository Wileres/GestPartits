<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case ManualSent = 'manual_sent';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case Outdated = 'outdated';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Esborrany',
            self::Queued => 'A la cua',
            self::Sending => 'Enviant',
            self::Sent => 'Enviat',
            self::ManualSent => 'Enviat manualment',
            self::Failed => 'Error',
            self::Unknown => 'Estat desconegut',
            self::Outdated => 'Desactualitzat',
            self::Discarded => 'Descartat',
        };
    }
}
