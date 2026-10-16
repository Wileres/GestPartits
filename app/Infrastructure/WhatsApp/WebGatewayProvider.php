<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\Messaging\OutgoingMessage;
use App\Domain\Messaging\ProviderCapabilities;
use App\Domain\Messaging\ProviderResult;
use App\Domain\Messaging\WhatsAppProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Via B (opcional, desactivada per defecte): passarel·la HTTP no oficial que publica amb un número
 * dedicat del club vinculat com a WhatsApp Web. Incompleix les condicions de WhatsApp i pot provocar
 * el bloqueig del número: només s'ha d'activar si el club n'accepta el risc.
 *
 * Contracte esperat de la passarel·la (adaptable):
 *   POST {WHATSAPP_GATEWAY_URL}/messages   Authorization: Bearer {WHATSAPP_GATEWAY_TOKEN}
 *   {"to": "120363…@g.us", "text": "…", "media": [{"filename": "…", "mime": "image/png", "data": "<base64>"}], "idempotency_key": "…"}
 *   → 2xx {"id": "…"}
 */
class WebGatewayProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly ?string $url, private readonly ?string $token) {}

    public function key(): string
    {
        return 'gateway';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['group', 'channel', 'phone'], supportsMedia: true, freeText: true, manual: false);
    }

    public function validateDestination(string $destinationType, ?string $externalId): ?string
    {
        $patterns = [
            'group' => ['/^\d{10,30}@g\.us$/', 'L\'identificador d\'un grup ha de ser de la forma 120363000000000000@g.us.'],
            'channel' => ['/^\d{10,30}@newsletter$/', 'L\'identificador d\'un canal ha de ser de la forma 120363000000000000@newsletter.'],
            'phone' => ['/^\+?\d{8,15}$/', 'El telèfon ha d\'estar en format internacional, p. ex. +34600000000.'],
        ];

        if (! isset($patterns[$destinationType])) {
            return 'La passarel·la no admet aquest tipus de destí.';
        }

        return $externalId !== null && preg_match($patterns[$destinationType][0], $externalId) === 1 ? null : $patterns[$destinationType][1];
    }

    public function send(OutgoingMessage $message): ProviderResult
    {
        if ($this->url === null || $this->url === '' || $this->token === null || $this->token === '') {
            return ProviderResult::failed('La passarel·la no està configurada.');
        }

        try {
            $response = Http::withToken($this->token)->acceptJson()->timeout(30)->post(rtrim($this->url, '/').'/messages', [
                'to' => $message->externalId,
                'text' => $message->body,
                'idempotency_key' => $message->idempotencyKey,
                'media' => array_map(fn (array $file) => [
                    'filename' => $file['filename'],
                    'mime' => $file['mime'],
                    'data' => base64_encode((string) file_get_contents($file['path'])),
                ], $message->attachments),
            ]);
        } catch (ConnectionException $e) {
            return ProviderResult::unknown('Sense resposta de la passarel·la: '.$e->getMessage());
        }

        $summary = ['status' => $response->status(), 'id' => $response->json('id')];

        return $response->successful()
            ? ProviderResult::sent((string) $response->json('id'), $summary)
            : ProviderResult::failed('La passarel·la ha respost amb l\'error '.$response->status().'.', $summary);
    }
}
