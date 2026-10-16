<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\Messaging\OutgoingMessage;
use App\Domain\Messaging\ProviderCapabilities;
use App\Domain\Messaging\ProviderResult;
use App\Domain\Messaging\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Simulació per a desenvolupament i proves: escriu el missatge al registre i el dona per enviat.
 */
class LogProvider implements WhatsAppProviderInterface
{
    public function key(): string
    {
        return 'log';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['group', 'channel', 'list', 'phone'], supportsMedia: true, freeText: true, manual: false);
    }

    public function validateDestination(string $destinationType, ?string $externalId): ?string
    {
        return null;
    }

    public function send(OutgoingMessage $message): ProviderResult
    {
        Log::info('WhatsApp (simulació)', [
            'type' => $message->destinationType,
            'chars' => mb_strlen($message->body),
            'attachments' => count($message->attachments),
            'key' => $message->idempotencyKey,
        ]);

        return ProviderResult::sent('sim-'.Str::lower(Str::random(12)), ['simulated' => true]);
    }
}
