<?php

namespace App\Rules;

use App\Support\Time;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * L'inici del partit ha d'estar dins l'horari operatiu (per defecte, de 09:00 a 21:00).
 */
class WithinOperatingHours implements ValidationRule
{
    public function __construct(private readonly string $firstKickoff, private readonly string $lastKickoff) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{2}:\d{2}$/', $value) !== 1) {
            return;
        }

        $minutes = Time::toMinutes($value);

        if ($minutes < Time::toMinutes($this->firstKickoff) || $minutes > Time::toMinutes($this->lastKickoff)) {
            $fail(__('messages.validation.operating_hours', ['from' => $this->firstKickoff, 'to' => $this->lastKickoff]));
        }
    }
}
