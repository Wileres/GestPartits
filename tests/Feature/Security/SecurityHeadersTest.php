<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_spa_page_sends_security_headers_and_a_strict_csp(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_api_responses_also_send_security_headers(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_session_cookie_is_http_only_and_same_site(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue((bool) config('session.encrypt'));
    }

    public function test_production_example_environment_requires_secure_cookies(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $example);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $example);
        $this->assertMatchesRegularExpression('/^HASH_DRIVER=argon2id$/m', $example);
    }

    public function test_env_file_is_ignored_by_git(): void
    {
        $this->assertMatchesRegularExpression('/^\.env$/m', (string) file_get_contents(base_path('.gitignore')));
    }

    public function test_private_storage_is_not_served_publicly(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/qualsevol.txt')->assertDontSee('qualsevol');
    }
}
