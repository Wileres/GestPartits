<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'schedule_id', 'schedule_version_id', 'day', 'page', 'total_pages', 'width', 'height',
    'disk', 'path', 'checksum', 'renderer', 'warnings', 'created_by',
])]
class GeneratedImage extends Model
{
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'page' => 'integer',
            'total_pages' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'warnings' => 'array',
        ];
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class);
    }
}
