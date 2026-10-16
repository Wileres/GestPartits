<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Elimina dels registres (context i missatge) contrasenyes, tokens, capçaleres d'autorització i telèfons.
 */
class RedactSensitiveData implements ProcessorInterface
{
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'authorization', 'cookie', 'api_key', 'external_id', 'remember'];

    /** Patrons dins del text: «Bearer …», «password=…» i telèfons internacionals. */
    private const PATTERNS = [
        '/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/i' => '$1[ocult]',
        '/((?:password|contrasenya|token|secret)["\']?\s*[:=]\s*["\']?)[^"\'\s,&}]+/i' => '$1[ocult]',
        '/\+\d{2}\s?\d{3}\s?\d{3}\s?\d{3}/' => '[telèfon]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $values[$key] = '[ocult]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redactArray($value);
            } elseif (is_string($value)) {
                $values[$key] = $this->redactString($value);
            }
        }

        return $values;
    }

    private function redactString(string $value): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $value);
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
