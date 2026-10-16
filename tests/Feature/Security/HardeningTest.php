<?php

namespace Tests\Feature\Security;

use App\Logging\RedactingTap;
use App\Logging\RedactSensitiveData;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Scheduling\ScheduleService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    public function test_session_is_regenerated_on_login_against_session_fixation(): void
    {
        User::factory()->create(['username' => 'gestor']);
        $this->startSession();
        $before = session()->getId();

        $this->spa()->postJson('/api/auth/login', ['username' => 'gestor', 'password' => UserFactory::PASSWORD])->assertOk();

        $this->assertNotSame($before, session()->getId());
    }

    public function test_hsts_is_sent_only_in_production_over_https(): void
    {
        $this->withoutVite();
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_logs_never_contain_passwords_tokens_or_phones(): void
    {
        $record = new LogRecord(new \DateTimeImmutable, 'app', Level::Error, 'Error amb Authorization: Bearer abc.def-123 i password=Secreta2026 per al +34 600 000 000', [
            'password' => 'Secreta2026',
            'request' => ['headers' => ['authorization' => 'Bearer xyz'], 'remember_token' => 'r'],
            'username' => 'gestor',
        ]);

        $clean = (new RedactSensitiveData)($record);

        $this->assertStringNotContainsString('abc.def-123', $clean->message);
        $this->assertStringNotContainsString('Secreta2026', $clean->message);
        $this->assertStringNotContainsString('600 000 000', $clean->message);
        $this->assertSame('[ocult]', $clean->context['password']);
        $this->assertSame('[ocult]', $clean->context['request']['headers']['authorization']);
        $this->assertSame('gestor', $clean->context['username']);
    }

    public function test_log_channels_use_the_redaction_tap(): void
    {
        $this->assertContains(RedactingTap::class, config('logging.channels.single.tap'));
        $this->assertContains(RedactingTap::class, config('logging.channels.daily.tap'));
    }

    public function test_password_reset_tokens_are_stored_hashed(): void
    {
        $user = User::factory()->create(['email' => 'gestor@example.org']);
        $token = Password::broker()->createToken($user);

        $stored = DB::table('password_reset_tokens')->where('email', 'gestor@example.org')->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(password_verify($token, $stored));
    }

    public function test_sensitive_operations_are_rate_limited(): void
    {
        $this->seedClub();
        $this->actingAs($this->manager(), 'web');
        $schedule = app(ScheduleService::class)->forMatchday($this->weekend());

        $statuses = [];
        for ($i = 0; $i < 22; $i++) {
            $statuses[] = $this->spa()->postJson("/api/schedules/{$schedule->id}/proposal", ['version' => $schedule->refresh()->lock_version])->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_audit_log_is_available_to_admins_with_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web');
        app(AuditLogger::class)->log('schedule.confirmed', null, null, ['version' => 3]);
        app(AuditLogger::class)->log('auth.login_failed', null, null, ['username' => 'algu']);

        $this->spa()->getJson('/api/audit-logs?event=schedule')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'schedule.confirmed')
            ->assertJsonPath('data.0.new_values.version', 3);

        $this->actingAs(User::factory()->create(), 'web');
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web')->spa()->getJson('/api/audit-logs')->assertForbidden();
        $this->assertGreaterThanOrEqual(2, AuditLog::query()->count());
    }

    public function test_forwarded_headers_are_trusted_only_from_configured_proxies(): void
    {
        Route::get('/api/_prova-esquema', fn (Request $request) => ['secure' => $request->isSecure()]);

        $this->getJson('/api/_prova-esquema', ['X-Forwarded-Proto' => 'https'])->assertJsonPath('secure', false);

        config(['gestpartits.trusted_proxies' => '127.0.0.1']);
        $this->getJson('/api/_prova-esquema', ['X-Forwarded-Proto' => 'https'])->assertJsonPath('secure', true);
    }
}
