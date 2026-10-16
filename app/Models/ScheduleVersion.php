<?php

namespace App\Models;

use App\Enums\ScheduleVersionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantània immutable de la programació (només insercions).
 */
#[Fillable(['schedule_id', 'number', 'kind', 'snapshot', 'conflicts', 'summary', 'created_by'])]
class ScheduleVersion extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'kind' => ScheduleVersionKind::class,
            'snapshot' => 'array',
            'conflicts' => 'array',
            'number' => 'integer',
        ];
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
