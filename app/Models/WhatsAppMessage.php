<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppProviderKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'whatsapp_messages')]
#[Fillable([
    'schedule_id', 'match_id', 'whatsapp_destination_id', 'whatsapp_template_id', 'schedule_version_id',
    'provider', 'body', 'body_hash', 'idempotency_key', 'status', 'attempts', 'provider_message_id',
    'provider_response', 'error_message', 'queued_at', 'sent_at', 'sent_by', 'created_by',
])]
class WhatsAppMessage extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => WhatsAppProviderKey::class,
            'status' => WhatsAppMessageStatus::class,
            'provider_response' => 'array',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<WhatsAppDestination, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(WhatsAppDestination::class, 'whatsapp_destination_id')->withTrashed();
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class);
    }

    /** @return HasMany<WhatsAppMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(WhatsAppMessageAttachment::class, 'whatsapp_message_id')->orderBy('sort_order');
    }
}
