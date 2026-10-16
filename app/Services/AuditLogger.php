<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Escriu a audit_logs eliminant qualsevol valor sensible.
 */
class AuditLogger
{
    /** Claus (o parts de clau) que mai no s'enregistren. */
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'remember', 'external_id', 'api_key', 'authorization'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(string $event, ?Model $subject = null, ?array $old = null, ?array $new = null, ?int $userId = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old === null ? null : $this->sanitize($old),
            'new_values' => $new === null ? null : $this->sanitize($new),
            'ip' => $this->request()->ip(),
            'user_agent' => Str::limit((string) $this->request()->userAgent(), 500, ''),
        ]);
    }

    /**
     * Registra els canvis d'un model just després de desar-lo.
     */
    public function logChanges(string $event, Model $subject): ?AuditLog
    {
        $changes = $subject->getChanges();
        unset($changes['updated_at'], $changes['updated_by']);

        if ($changes === []) {
            return null;
        }

        $old = array_intersect_key($subject->getPrevious(), $changes);

        return $this->log($event, $subject, $old, $changes);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $clean[$key] = '[ocult]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }

    private function request(): Request
    {
        return request();
    }

    private function isSensitive(string $key): bool
    {
        $key = Str::lower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
