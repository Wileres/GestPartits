<?php

namespace Tests\Unit\Services;

use App\Services\AuditLogger;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    public function test_sensitive_values_are_removed_recursively(): void
    {
        $clean = app(AuditLogger::class)->sanitize([
            'username' => 'gestor',
            'password' => 'secret',
            'remember_token' => 'abc',
            'provider' => ['api_key' => 'xyz', 'status' => 'ok'],
            'external_id' => '34600000000',
        ]);

        $this->assertSame([
            'username' => 'gestor',
            'password' => '[ocult]',
            'remember_token' => '[ocult]',
            'provider' => ['api_key' => '[ocult]', 'status' => 'ok'],
            'external_id' => '[ocult]',
        ], $clean);
    }
}
