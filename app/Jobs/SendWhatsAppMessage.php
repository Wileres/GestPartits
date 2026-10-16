<?php

namespace App\Jobs;

use App\Domain\Messaging\OutgoingMessage;
use App\Domain\Messaging\ProviderResult;
use App\Enums\WhatsAppMessageStatus;
use App\Infrastructure\WhatsApp\WhatsAppProviderRegistry;
use App\Models\WhatsAppMessage;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Envia un missatge de WhatsApp que és a la cua. Un sol intent: si no se sap què ha passat,
 * el missatge queda en estat «desconegut» i no es torna a enviar sense que el gestor ho decideixi.
 */
class SendWhatsAppMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly int $messageId) {}

    public function uniqueId(): string
    {
        return (string) $this->messageId;
    }

    public function handle(WhatsAppProviderRegistry $providers, AuditLogger $audit): void
    {
        // Només es pot passar de «a la cua» a «enviant» un cop.
        $claimed = DB::transaction(function (): ?WhatsAppMessage {
            $message = WhatsAppMessage::query()->with(['destination', 'template', 'attachments.generatedImage'])->whereKey($this->messageId)->lockForUpdate()->first();
            if ($message === null || $message->status !== WhatsAppMessageStatus::Queued) {
                return null;
            }

            $message->forceFill(['status' => WhatsAppMessageStatus::Sending, 'attempts' => $message->attempts + 1])->save();

            return $message;
        });

        if ($claimed === null) {
            return;
        }

        try {
            $result = $providers->for($claimed->provider)->send(new OutgoingMessage(
                destinationType: $claimed->destination->destination_type->value,
                externalId: $claimed->destination->external_id,
                body: $claimed->body,
                idempotencyKey: $claimed->idempotency_key,
                attachments: $claimed->attachments->map(fn ($a) => [
                    'path' => Storage::disk($a->generatedImage->disk)->path($a->generatedImage->path),
                    'mime' => 'image/png',
                    'filename' => basename($a->generatedImage->path),
                ])->all(),
                providerTemplate: $claimed->template?->provider_template_name,
            ));
        } catch (Throwable $e) {
            $result = ProviderResult::unknown(mb_substr($e->getMessage(), 0, 500));
        }

        $status = match ($result->status) {
            ProviderResult::SENT => WhatsAppMessageStatus::Sent,
            ProviderResult::FAILED => WhatsAppMessageStatus::Failed,
            default => WhatsAppMessageStatus::Unknown,
        };

        $claimed->forceFill([
            'status' => $status,
            'provider_message_id' => $result->providerMessageId,
            'provider_response' => $audit->sanitize($result->response),
            'error_message' => $result->error === null ? null : mb_substr($result->error, 0, 1000),
            'sent_at' => $status === WhatsAppMessageStatus::Sent ? now() : null,
        ])->save();

        $audit->log('whatsapp.'.$status->value, $claimed, null, ['destination_id' => $claimed->whatsapp_destination_id, 'provider' => $claimed->provider->value], $claimed->sent_by);
    }
}
