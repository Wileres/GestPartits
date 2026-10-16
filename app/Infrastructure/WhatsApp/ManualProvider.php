<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\Messaging\OutgoingMessage;
use App\Domain\Messaging\ProviderCapabilities;
use App\Domain\Messaging\ProviderResult;
use App\Domain\Messaging\WhatsAppProviderInterface;
use LogicException;

/**
 * Via A (per defecte): l'aplicació prepara el text i el gestor el publica (copiar / obrir WhatsApp)
 * i el marca com a enviat. Serveix per a grups, canals i llistes existents.
 */
class ManualProvider implements WhatsAppProviderInterface
{
    public function key(): string
    {
        return 'manual';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['group', 'channel', 'list', 'phone'], supportsMedia: false, freeText: true, manual: true);
    }

    public function validateDestination(string $destinationType, ?string $externalId): ?string
    {
        return null;
    }

    public function send(OutgoingMessage $message): ProviderResult
    {
        throw new LogicException('El proveïdor manual no envia missatges: el gestor els publica i els marca com a enviats.');
    }
}
