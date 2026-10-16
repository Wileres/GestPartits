<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Proxys de confiança llegits de la configuració (compatible amb `php artisan config:cache`).
 */
class TrustConfiguredProxies extends TrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $proxies = config('gestpartits.trusted_proxies');

        return match ($proxies) {
            null, '' => null,
            '*' => '*',
            default => array_map('trim', explode(',', (string) $proxies)),
        };
    }
}
