<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Pending = 'pending';
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Rest = 'rest';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendent',
            self::Proposed => 'Proposat',
            self::Confirmed => 'Confirmat',
            self::Postponed => 'Ajornat',
            self::Cancelled => 'Cancel·lat',
            self::Rest => 'Descansa',
        };
    }

    /** El partit es jugarà (i, si és a casa, necessita horari). */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Proposed, self::Confirmed], true);
    }
}
