<?php

namespace App\Domain\Messaging;

/**
 * Missatge preparat per enviar-lo amb un proveïdor.
 */
final readonly class OutgoingMessage
{
    /**
     * @param  list<array{path: string, mime: string, filename: string}>  $attachments  Fitxers locals (imatges de la jornada).
     */
    public function __construct(
        public string $destinationType,
        public ?string $externalId,
        public string $body,
        public string $idempotencyKey,
        public array $attachments = [],
        public ?string $providerTemplate = null,
    ) {}
}
