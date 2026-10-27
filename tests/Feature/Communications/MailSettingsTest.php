<?php

namespace Tests\Feature\Communications;

use App\Mail\PhpMailerTransport;
use App\Models\ApplicationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    private array $payload = [
        'host' => 'smtp.club.example', 'port' => 465, 'encryption' => 'ssl', 'username' => 'correu@club.example',
        'password' => 'secret-pass', 'from_address' => 'avisos@club.example', 'from_name' => 'JSPM',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
    }

    public function test_only_the_administrator_can_see_or_change_the_mail_settings(): void
    {
        $this->actingAs($this->manager(), 'web');

        $this->spa()->getJson('/api/mail-settings')->assertForbidden();
        $this->spa()->putJson('/api/mail-settings', $this->payload)->assertForbidden();
        $this->spa()->postJson('/api/mail-settings/test')->assertForbidden();
    }

    public function test_administrator_saves_the_settings_and_the_password_is_stored_encrypted_and_never_returned(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->spa()->putJson('/api/mail-settings', $this->payload)->assertOk()
            ->assertJsonPath('data.host', 'smtp.club.example')->assertJsonPath('data.has_password', true)->assertJsonMissingPath('data.password');

        $stored = ApplicationSetting::get('mail');
        $this->assertNotSame('secret-pass', $stored['password']);
        $this->assertStringNotContainsString('secret-pass', json_encode($this->spa()->getJson('/api/mail-settings')->json()));

        // Contrasenya en blanc: es conserva l'anterior.
        $this->spa()->putJson('/api/mail-settings', [...$this->payload, 'password' => '', 'port' => 587, 'encryption' => 'tls'])->assertOk();
        $this->assertSame($stored['password'], ApplicationSetting::get('mail')['password']);
        $this->assertSame(587, ApplicationSetting::get('mail')['port']);
    }

    public function test_the_saved_settings_configure_the_mail_transport(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->spa()->putJson('/api/mail-settings', $this->payload)->assertOk();

        $transport = Mail::mailer('phpmailer')->getSymfonyTransport();
        $this->assertInstanceOf(PhpMailerTransport::class, $transport);
        $this->assertSame('phpmailer://smtp.club.example:465', (string) $transport);
        $this->assertSame('avisos@club.example', config('mail.from.address'));
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->spa()->putJson('/api/mail-settings', [...$this->payload, 'port' => 0, 'encryption' => 'x', 'from_address' => 'no'])
            ->assertUnprocessable()->assertJsonValidationErrors(['port', 'encryption', 'from_address']);
    }

    public function test_a_test_email_can_be_sent(): void
    {
        config(['mail.default' => 'array']);
        $this->actingAs($this->admin(), 'web');

        $this->spa()->postJson('/api/mail-settings/test', ['email' => 'prova@club.example'])->assertOk()->assertJsonPath('data.sent_to', 'prova@club.example');
    }
}
