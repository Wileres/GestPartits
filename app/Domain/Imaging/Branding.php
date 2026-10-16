<?php

namespace App\Domain\Imaging;

/**
 * Identitat visual del club per a les imatges.
 */
final readonly class Branding
{
    public function __construct(
        public string $clubName,
        public string $primaryColor,
        public string $primaryDarkColor,
        /** Escut en PNG (bytes) o null per fer servir l'escut genèric. */
        public ?string $logoPng = null,
    ) {}
}
