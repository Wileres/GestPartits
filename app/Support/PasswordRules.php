<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    /**
     * Política de contrasenyes de l'aplicació.
     */
    public static function strong(): Password
    {
        return Password::min((int) config('gestpartits.auth.password_min_length', 12))
            ->letters()
            ->mixedCase()
            ->numbers();
    }
}
