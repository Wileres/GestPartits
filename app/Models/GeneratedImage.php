<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kind', 'matchday_id', 'batch_id', 'schedule_id', 'schedule_version_id', 'day', 'page', 'total_pages', 'width', 'height',
    'disk', 'path', 'checksum', 'renderer', 'warnings', 'options', 'created_by',
])]
class GeneratedImage extends Model
{
    /** Imatge del calendari de partits d'un dia. */
    public const KIND_SCHEDULE = 'schedule';

    /** Imatge resum dels resultats de la jornada. */
    public const KIND_RESULTS = 'results';

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'page' => 'integer',
            'total_pages' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'warnings' => 'array',
            'options' => 'array',
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

    /** @return BelongsTo<Matchday, $this> */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<WhatsAppMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(WhatsAppMessageAttachment::class, 'generated_image_id');
    }

    /** Nom del fitxer en descarregar-la: «jornada-2026-10-17-1.png» o «resultats-2026-10-17.png». */
    public function downloadName(): string
    {
        if ($this->kind === self::KIND_RESULTS) {
            return $this->total_pages > 1
                ? sprintf('resultats-%s-%d.png', $this->day->toDateString(), $this->page)
                : sprintf('resultats-%s.png', $this->day->toDateString());
        }

        return sprintf('jornada-%s-%d.png', $this->day->toDateString(), $this->page);
    }
}
