<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\Messaging\OutgoingMessage;
use App\Domain\Messaging\ProviderCapabilities;
use App\Domain\Messaging\ProviderResult;
use App\Domain\Messaging\WhatsAppProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business Platform (Cloud API de Meta). Només envia a números (no publica en grups existents).
 * Els missatges que inicia l'empresa fora de la finestra de 24 h han de fer servir una plantilla aprovada
 * per Meta: si la plantilla del club té «provider_template_name», s'envia aquesta plantilla amb el text com a
 * paràmetre; si no, s'envia com a text lliure.
 */
class MetaCloudProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly ?string $phoneNumberId, private readonly ?string $token, private readonly string $apiVersion = 'v21.0') {}

    public function key(): string
    {
        return 'meta';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['phone'], supportsMedia: false, freeText: false, manual: false);
    }

    public function validateDestination(string $destinationType, ?string $externalId): ?string
    {
        if ($destinationType !== 'phone') {
            return 'WhatsApp Business (Meta) només pot enviar a números de telèfon, no a grups ni canals.';
        }

        return $externalId !== null && preg_match('/^\+?\d{8,15}$/', $externalId) === 1 ? null : 'El telèfon ha d\'estar en format internacional, p. ex. +34600000000.';
    }

    public function send(OutgoingMessage $message): ProviderResult
    {
        if ($this->phoneNumberId === null || $this->phoneNumberId === '' || $this->token === null || $this->token === '') {
            return ProviderResult::failed('WhatsApp Business (Meta) no està configurat.');
        }

        $payload = ['messaging_product' => 'whatsapp', 'to' => ltrim((string) $message->externalId, '+')];
        $payload += $message->providerTemplate === null
            ? ['type' => 'text', 'text' => ['body' => $message->body]]
            : ['type' => 'template', 'template' => [
                'name' => $message->providerTemplate,
                'language' => ['code' => 'ca'],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $message->body]]]],
            ]];

        try {
            $response = Http::withToken($this->token)->acceptJson()->timeout(30)
                ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);
        } catch (ConnectionException $e) {
            return ProviderResult::unknown('Sense resposta de Meta: '.$e->getMessage());
        }

        $id = $response->json('messages.0.id');
        $summary = ['status' => $response->status(), 'id' => $id, 'error' => $response->json('error.message')];

        return $response->successful() && $id !== null
            ? ProviderResult::sent((string) $id, $summary)
            : ProviderResult::failed('Meta ha rebutjat el missatge ('.$response->status().').', $summary);
    }
}
