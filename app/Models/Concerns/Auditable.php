<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Registra a l'auditoria les altes, modificacions i baixes del model.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            $values = $model->attributesToArray();
            unset($values['created_at'], $values['updated_at'], $values['created_by'], $values['updated_by']);

            app(AuditLogger::class)->log($model->auditEventPrefix().'.created', $model, null, $values);
        });

        static::updated(function ($model): void {
            app(AuditLogger::class)->logChanges($model->auditEventPrefix().'.updated', $model);
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->log($model->auditEventPrefix().'.deleted', $model, $model->only(['id']));
        });
    }

    public function auditEventPrefix(): string
    {
        return Str::snake(class_basename($this));
    }
}
