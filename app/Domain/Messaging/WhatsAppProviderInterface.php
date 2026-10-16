<?php

namespace App\Domain\Messaging;

/**
 * Proveïdor d'enviament de WhatsApp. La lògica de negoci (NotificationService) només coneix aquesta
 * interfície: manual (copiar i enganxar), passarel·la, WhatsApp Business (Meta), Twilio o simulació.
 */
interface WhatsAppProviderInterface
{
    public function key(): string;

    public function capabilities(): ProviderCapabilities;

    /**
     * Comprova l'identificador d'un destí. Retorna el missatge d'error en català o null si és vàlid.
     */
    public function validateDestination(string $destinationType, ?string $externalId): ?string;

    public function send(OutgoingMessage $message): ProviderResult;
}
