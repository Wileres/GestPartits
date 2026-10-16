<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:gestor|127.0.0.1');
    }

    public function test_user_can_log_in_with_username_and_password(): void
    {
        $user = User::factory()->create(['username' => 'gestor']);

        $this->spa()->postJson('/api/auth/login', ['username' => 'gestor', 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.username', 'gestor')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_in_catalan_and_audited_without_the_password(): void
    {
        User::factory()->create(['username' => 'gestor']);

        $this->spa()->postJson('/api/auth/login', ['username' => 'gestor', 'password' => 'incorrecta-123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'L\'usuari o la contrasenya no són correctes.');

        $this->assertGuest('web');

        $log = AuditLog::query()->where('event', 'auth.login_failed')->sole();
        $this->assertSame(['username' => 'gestor'], $log->new_values);
        $this->assertStringNotContainsString('incorrecta-123', json_encode($log->toArray()));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'gestor']);

        $this->spa()->postJson('/api/auth/login', ['username' => 'gestor', 'password' => UserFactory::PASSWORD])
            ->assertUnprocessable();

        $this->assertGuest('web');
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        User::factory()->create(['username' => 'gestor']);

        for ($i = 0; $i < 5; $i++) {
            $this->spa()->postJson('/api/auth/login', ['username' => 'gestor', 'password' => 'incorrecta'])->assertUnprocessable();
        }

        // Fins i tot amb la contrasenya correcta, l'usuari queda bloquejat temporalment.
        $this->spa()->postJson('/api/auth/login', ['username' => 'gestor', 'password' => UserFactory::PASSWORD])
            ->assertStatus(429)
            ->assertJsonPath('errors.username.0', fn (string $message) => str_starts_with($message, 'Massa intents d\'inici de sessió.'));

        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.lockout']);
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->spa()->postJson('/api/auth/logout')->assertNoContent();

        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->spa()->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Cal iniciar la sessió.');
    }

    public function test_me_returns_admin_permissions(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')->spa()->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.permissions.manage_users', true)
            ->assertJsonPath('data.permissions.manage_settings', true);
    }

    public function test_me_returns_manager_permissions(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->spa()->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.permissions.manage_users', false)
            ->assertJsonPath('data.permissions.manage_settings', false);
    }

    public function test_deactivated_user_with_open_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $user->forceFill(['is_active' => false])->save();

        $this->spa()->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'inactive_user');
    }
}
