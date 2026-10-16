<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'usuari',
            'password' => 'contrasenya',
        ];
    }

    /**
     * Valida les credencials aplicant el límit d'intents per usuari i IP.
     *
     * @throws ValidationException
     */
    public function authenticate(AuditLogger $audit): User
    {
        $this->ensureIsNotRateLimited($audit);

        $credentials = [
            'username' => $this->string('username')->trim()->value(),
            'password' => $this->string('password')->value(),
            'is_active' => true,
        ];

        if (! Auth::guard('web')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), (int) config('gestpartits.auth.lockout_seconds'));

            $audit->log('auth.login_failed', null, null, ['username' => $credentials['username']]);

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(AuditLogger $audit): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), (int) config('gestpartits.auth.max_login_attempts'))) {
            return;
        }

        event(new Lockout($this));

        $audit->log('auth.lockout', null, null, ['username' => $this->string('username')->trim()->value()]);

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => __('auth.throttle', ['seconds' => $seconds]),
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->string('username')->trim()->value())).'|'.$this->ip();
    }
}
