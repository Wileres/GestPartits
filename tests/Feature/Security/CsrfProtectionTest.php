<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel desactiva la comprovació CSRF a les proves; aquí es força per verificar-la.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_changing_request_without_csrf_token_is_rejected_in_catalan(): void
    {
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $this->actingAs(User::factory()->create(), 'web')
            ->spa()
            ->postJson('/api/auth/logout')
            ->assertStatus(419)
            ->assertJsonPath('message', 'La sessió ha caducat. Recarrega la pàgina i torna-ho a provar.');

        $this->assertAuthenticated('web');
    }
}
