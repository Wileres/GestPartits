<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_initial_password_is_forced_to_change_it(): void
    {
        $user = User::factory()->admin()->mustChangePassword()->create();

        $this->actingAs($user, 'web')->spa()->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');

        // Pot consultar qui és i tancar la sessió.
        $this->spa()->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.must_change_password', true);
    }

    public function test_user_can_change_password_and_then_use_the_application(): void
    {
        $user = User::factory()->admin()->mustChangePassword()->create();

        $this->actingAs($user, 'web')->spa()->postJson('/api/auth/password/change', [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'NovaContrasenya2026',
            'password_confirmation' => 'NovaContrasenya2026',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('NovaContrasenya2026', $user->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_changed', 'user_id' => $user->id]);

        $this->spa()->getJson('/api/users')->assertOk();
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user, 'web')->spa()->postJson('/api/auth/password/change', [
            'current_password' => 'no-es-aquesta',
            'password' => 'NovaContrasenya2026',
            'password_confirmation' => 'NovaContrasenya2026',
        ])->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'La contrasenya no és correcta.');
    }

    public function test_new_password_must_follow_the_policy(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user, 'web')->spa()->postJson('/api/auth/password/change', [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->spa()->postJson('/api/auth/password/change', [
            'current_password' => UserFactory::PASSWORD,
            'password' => UserFactory::PASSWORD,
            'password_confirmation' => UserFactory::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
