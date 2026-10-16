<?php

namespace App\Http\Resources;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppProviderKey;
use App\Models\WhatsAppMessage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppMessage
 */
class WhatsAppMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $manual = $this->provider === WhatsAppProviderKey::Manual;

        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'team' => $this->match?->team?->name,
            'opponent' => $this->match?->opponent,
            'is_summary' => $this->match_id === null,
            'destination' => [
                'id' => $this->destination->id,
                'label' => $this->destination->label,
                'type' => $this->destination->destination_type->value,
                'type_label' => $this->destination->destination_type->label(),
                'team' => $this->destination->team?->name,
            ],
            'provider' => $this->provider->value,
            'provider_label' => $this->provider->label(),
            'is_manual' => $manual,
            'body' => $this->body,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'attempts' => $this->attempts,
            'error_message' => $this->error_message,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'attachments' => $this->attachments->map(fn ($a) => [
                'id' => $a->generatedImage->id,
                'url' => url("/api/generated-images/{$a->generatedImage->id}"),
                'day' => $a->generatedImage->day->toDateString(),
                'page' => $a->generatedImage->page,
            ])->values(),
            'share_url' => $manual ? app(NotificationService::class)->shareUrl($this->resource) : null,
            'can_edit' => $this->status === WhatsAppMessageStatus::Draft,
            'can_send' => ! $manual && $this->status === WhatsAppMessageStatus::Draft,
            'can_mark_sent' => ($manual && $this->status === WhatsAppMessageStatus::Draft) || $this->status === WhatsAppMessageStatus::Unknown,
            'can_discard' => in_array($this->status, [WhatsAppMessageStatus::Draft, WhatsAppMessageStatus::Failed], true),
        ];
    }
}
