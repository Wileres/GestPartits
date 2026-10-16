<?php

namespace App\Rules;

use App\Support\Time;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * L'hora ha de caure en un múltiple del pas de la graella (per defecte, 15 minuts).
 */
class TimeOnGrid implements ValidationRule
{
    public function __construct(private readonly int $stepMinutes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{2}:\d{2}$/', $value) !== 1) {
            return; // El format el valida date_format:H:i.
        }

        if (! Time::isOnGrid($value, $this->stepMinutes)) {
            $fail($this->stepMinutes === 15
                ? __('messages.validation.quarter_hour')
                : __('messages.validation.time_step', ['step' => $this->stepMinutes]));
        }
    }
}
