<?php

namespace App\Infrastructure\WhatsApp;

use App\Domain\Messaging\WhatsAppProviderInterface;
use App\Enums\WhatsAppProviderKey;
use InvalidArgumentException;

/**
 * Tria el proveïdor de cada destí. La passarel·la (via B) només està disponible si s'activa al .env.
 */
class WhatsAppProviderRegistry
{
    /** @var array<string, WhatsAppProviderInterface> */
    private array $providers = [];

    public function register(WhatsAppProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function for(WhatsAppProviderKey|string $key): WhatsAppProviderInterface
    {
        $key = $key instanceof WhatsAppProviderKey ? $key->value : $key;

        return $this->providers[$key] ?? throw new InvalidArgumentException("Proveïdor de WhatsApp no disponible: {$key}");
    }

    public function isAvailable(WhatsAppProviderKey|string $key): bool
    {
        return isset($this->providers[$key instanceof WhatsAppProviderKey ? $key->value : $key]);
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->providers);
    }
}
