<?php

namespace App\Support;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Configuració del correu (SMTP) que gestiona l'administrador des de l'aplicació. Si no s'ha desat res,
 * s'usen els valors del fitxer .env. La contrasenya es desa xifrada i mai no es retorna.
 */
class MailSettings
{
    private const KEY = 'mail';

    /**
     * Valors efectius (desats o, si no n'hi ha, els del .env).
     *
     * @return array{host: string, port: int, encryption: string, username: string, from_address: string, from_name: string, has_password: bool, configured: bool}
     */
    public function values(): array
    {
        $saved = $this->saved();

        return [
            'host' => (string) ($saved['host'] ?? config('mail.mailers.phpmailer.host')),
            'port' => (int) ($saved['port'] ?? config('mail.mailers.phpmailer.port')),
            'encryption' => (string) ($saved['encryption'] ?? (config('mail.mailers.phpmailer.encryption') ?: 'none')),
            'username' => (string) ($saved['username'] ?? config('mail.mailers.phpmailer.username')),
            'from_address' => (string) ($saved['from_address'] ?? config('mail.from.address')),
            'from_name' => (string) ($saved['from_name'] ?? config('mail.from.name')),
            'has_password' => ($saved['password'] ?? config('mail.mailers.phpmailer.password')) !== null && ($saved['password'] ?? config('mail.mailers.phpmailer.password')) !== '',
            'configured' => $saved !== [],
        ];
    }

    /**
     * @param  array{host: string, port: int, encryption: string, username?: string|null, password?: string|null, from_address: string, from_name?: string|null}  $input
     */
    public function save(array $input, ?int $userId): void
    {
        $saved = $this->saved();
        $password = $input['password'] ?? null;

        ApplicationSetting::put(self::KEY, [
            'host' => $input['host'],
            'port' => $input['port'],
            'encryption' => $input['encryption'],
            'username' => $input['username'] ?? '',
            'password' => $password === null || $password === '' ? ($saved['password'] ?? null) : Crypt::encryptString($password),
            'from_address' => $input['from_address'],
            'from_name' => $input['from_name'] ?? '',
        ], $userId);

        // El següent correu ja ha de fer servir la nova configuració.
        Mail::forgetMailers();
    }

    /**
     * Aplica la configuració desada sobre la del transport PHPMailer (i el remitent global).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function apply(array $config): array
    {
        $saved = $this->saved();

        if ($saved === []) {
            return $config;
        }

        config(['mail.from.address' => $saved['from_address'] ?? config('mail.from.address'), 'mail.from.name' => ($saved['from_name'] ?? '') !== '' ? $saved['from_name'] : config('mail.from.name')]);

        return [
            ...$config,
            'host' => $saved['host'] ?? $config['host'],
            'port' => (int) ($saved['port'] ?? $config['port']),
            'encryption' => ($saved['encryption'] ?? 'tls') === 'none' ? null : $saved['encryption'],
            'username' => $saved['username'] ?? '',
            'password' => $this->password($saved),
        ];
    }

    /** @return array<string, mixed> */
    private function saved(): array
    {
        try {
            $value = ApplicationSetting::get(self::KEY, []);
        } catch (Throwable) {
            // Sense base de dades (per exemple abans de migrar): s'usa el .env.
            return [];
        }

        return is_array($value) ? $value : [];
    }

    /** @param  array<string, mixed>  $saved */
    private function password(array $saved): ?string
    {
        if (($saved['password'] ?? null) === null) {
            return null;
        }

        try {
            return Crypt::decryptString($saved['password']);
        } catch (DecryptException) {
            return null;
        }
    }
}
