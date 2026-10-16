<?php

namespace App\Domain\Messaging;

/**
 * Resultat d'un enviament. «unknown» vol dir que no se sap si ha arribat (p. ex. temps d'espera esgotat):
 * no es reintenta automàticament per no duplicar el missatge.
 */
final readonly class ProviderResult
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $response  Resposta del proveïdor sense dades sensibles.
     */
    public function __construct(
        public string $status,
        public ?string $providerMessageId = null,
        public array $response = [],
        public ?string $error = null,
    ) {}

    public static function sent(?string $id, array $response = []): self
    {
        return new self(self::SENT, $id, $response);
    }

    public static function failed(string $error, array $response = []): self
    {
        return new self(self::FAILED, null, $response, $error);
    }

    public static function unknown(string $error): self
    {
        return new self(self::UNKNOWN, null, [], $error);
    }
}
