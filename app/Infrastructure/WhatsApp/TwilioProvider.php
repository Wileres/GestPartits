<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\Messaging\OutgoingMessage;
use App\Domain\Messaging\ProviderCapabilities;
use App\Domain\Messaging\ProviderResult;
use App\Domain\Messaging\WhatsAppProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Twilio per a WhatsApp. Com Meta, només envia a números (whatsapp:+34…).
 */
class TwilioProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly ?string $accountSid, private readonly ?string $authToken, private readonly ?string $from) {}

    public function key(): string
    {
        return 'twilio';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['phone'], supportsMedia: false, freeText: true, manual: false);
    }

    public function validateDestination(string $destinationType, ?string $externalId): ?string
    {
        if ($destinationType !== 'phone') {
            return 'Twilio només pot enviar a números de telèfon, no a grups ni canals.';
        }

        return $externalId !== null && preg_match('/^\+\d{8,15}$/', $externalId) === 1 ? null : 'El telèfon ha d\'estar en format internacional, p. ex. +34600000000.';
    }

    public function send(OutgoingMessage $message): ProviderResult
    {
        if ($this->accountSid === null || $this->accountSid === '' || $this->authToken === null || $this->from === null || $this->from === '') {
            return ProviderResult::failed('Twilio no està configurat.');
        }

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)->asForm()->timeout(30)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'From' => 'whatsapp:'.$this->from,
                    'To' => 'whatsapp:'.$message->externalId,
                    'Body' => $message->body,
                ]);
        } catch (ConnectionException $e) {
            return ProviderResult::unknown('Sense resposta de Twilio: '.$e->getMessage());
        }

        $summary = ['status' => $response->status(), 'sid' => $response->json('sid'), 'error' => $response->json('message')];

        return $response->successful()
            ? ProviderResult::sent((string) $response->json('sid'), $summary)
            : ProviderResult::failed('Twilio ha rebutjat el missatge ('.$response->status().').', $summary);
    }
}
