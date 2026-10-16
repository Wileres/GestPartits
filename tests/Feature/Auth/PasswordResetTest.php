<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_an_active_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'gestor@example.org']);

        $this->spa()->postJson('/api/auth/password/forgot', ['email' => 'Gestor@Example.org'])
            ->assertOk()
            ->assertJsonPath('message', __('passwords.sent_generic'));

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Restabliment de la contrasenya'
                && str_contains($mail->actionUrl, '/restableix-contrasenya?token=');
        });
    }

    public function test_response_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();

        $this->spa()->postJson('/api/auth/password/forgot', ['email' => 'ningu@example.org'])
            ->assertOk()
            ->assertJsonPath('message', __('passwords.sent_generic'));

        Notification::assertNothingSent();
    }

    public function test_inactive_users_do_not_receive_a_reset_link(): void
    {
        Notification::fake();
        User::factory()->inactive()->create(['email' => 'baixa@example.org']);

        $this->spa()->postJson('/api/auth/password/forgot', ['email' => 'baixa@example.org'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->mustChangePassword()->create(['email' => 'gestor@example.org']);

        $this->spa()->postJson('/api/auth/password/forgot', ['email' => 'gestor@example.org']);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->spa()->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'gestor@example.org',
            'password' => 'RestablertaNova2026',
            'password_confirmation' => 'RestablertaNova2026',
        ])->assertOk()->assertJsonPath('message', 'S\'ha restablert la contrasenya.');

        $user->refresh();
        $this->assertTrue(Hash::check('RestablertaNova2026', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_reset', 'user_id' => $user->id]);
    }

    public function test_invalid_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'gestor@example.org']);

        $this->spa()->postJson('/api/auth/password/reset', [
            'token' => 'fals',
            'email' => 'gestor@example.org',
            'password' => 'RestablertaNova2026',
            'password_confirmation' => 'RestablertaNova2026',
        ])->assertUnprocessable()->assertJsonPath('errors.email.0', 'L\'enllaç per restablir la contrasenya no és vàlid o ha caducat.');
    }
}
