<?php

namespace App\Domain\Messaging;

/**
 * Què permet cada proveïdor. La interfície ho fa servir per explicar al gestor què pot fer.
 */
final readonly class ProviderCapabilities
{
    /**
     * @param  list<string>  $destinationTypes  Tipus de destí admesos (group, channel, phone, list).
     */
    public function __construct(
        public array $destinationTypes,
        public bool $supportsMedia,
        public bool $freeText,
        public bool $manual,
    ) {}

    public function supports(string $destinationType): bool
    {
        return in_array($destinationType, $this->destinationTypes, true);
    }
}
