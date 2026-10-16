<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Draft = 'draft';
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Esborrany',
            self::Proposed => 'Proposta',
            self::Confirmed => 'Confirmada',
            self::NeedsReview => 'Programació pendent de revisar',
        };
    }
}
