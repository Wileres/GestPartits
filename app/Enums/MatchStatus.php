<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposat',
            self::Confirmed => 'Confirmat',
            self::Cancelled => 'Cancel·lat',
        };
    }

    /** El partit es jugarà (i, si és a casa, necessita horari). */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }

    /**
     * Estat d'un partit nou.
     */
    public static function default(): self
    {
        return self::Confirmed;
    }
}
