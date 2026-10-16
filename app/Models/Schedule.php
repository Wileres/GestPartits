<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['matchday_id', 'venue_id', 'status'])]
class Schedule extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ScheduleStatus::class,
            'lock_version' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Matchday, $this> */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<ScheduleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ScheduleVersion::class)->orderBy('number');
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function confirmedVersion(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'confirmed_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return HasMany<GeneratedImage, $this> */
    public function generatedImages(): HasMany
    {
        return $this->hasMany(GeneratedImage::class);
    }

    /** @return HasMany<WhatsAppMessage, $this> */
    public function whatsAppMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }
}
