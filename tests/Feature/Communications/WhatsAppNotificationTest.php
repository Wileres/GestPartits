<?php

namespace Tests\Feature\Communications;

use App\Enums\WhatsAppMessageStatus;
use App\Infrastructure\WhatsApp\WhatsAppProviderRegistry;
use App\Models\GameMatch;
use App\Models\Schedule;
use App\Models\WhatsAppDestination;
use App\Models\WhatsAppMessage;
use App\Services\ImageService;
use App\Services\Scheduling\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use ConfirmsSchedule, RefreshDatabase, SeedsClub;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        Storage::fake('local');
        $this->actingAs($this->manager(), 'web');

        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $this->schedule = $this->confirmed($matchday);
    }

    private function destination(string $team, string $provider = 'manual', string $type = 'group', ?string $externalId = null): WhatsAppDestination
    {
        return WhatsAppDestination::query()->create([
            'scope' => 'team', 'team_id' => $this->team($team)->id, 'label' => 'Grup '.$team, 'provider' => $provider,
            'destination_type' => $type, 'external_id' => $externalId, 'is_active' => true,
        ]);
    }

    private function preview(): array
    {
        return $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/preview")->assertOk()->json('data');
    }

    private function useGateway(): void
    {
        config(['gestpartits.whatsapp.gateway' => ['enabled' => true, 'url' => 'https://passarella.example.org', 'token' => 'secret-token']]);
        $this->app->forgetInstance(WhatsAppProviderRegistry::class);
    }

    public function test_preview_requires_a_confirmed_schedule(): void
    {
        $other = $this->weekend('2026-10-24');
        $schedule = app(ScheduleService::class)->forMatchday($other);

        $this->spa()->postJson("/api/schedules/{$schedule->id}/whatsapp/preview")->assertUnprocessable();
    }

    public function test_preview_prepares_catalan_messages_per_team_destination_without_sending(): void
    {
        $this->destination('alevi-a');
        $this->destination('cadet-a', 'log', 'phone', '+34600000000');

        $messages = collect($this->preview());

        $alevi = $messages->firstWhere('team', 'Aleví A');
        $this->assertSame('draft', $alevi['status']);
        $this->assertStringContainsString('🏠 Aleví A - CF Olot', $alevi['body']);
        $this->assertStringContainsString('📅 dissabte 17/10/2026', $alevi['body']);
        $this->assertStringContainsString('🕐 Convocatòria: 10:30', $alevi['body']);
        $this->assertStringContainsString('📍 Camp del Joventut Sant Pere Màrtir', $alevi['body']);
        $this->assertTrue($alevi['is_manual']);
        $this->assertStringStartsWith('https://api.whatsapp.com/send?text=', $alevi['share_url']);

        $cadet = $messages->firstWhere('team', 'Cadet A');
        $this->assertStringContainsString('🚌 UE Figueres - Cadet A', $cadet['body']);
        $this->assertStringContainsString('🗺️ Carrer del Futbol, 1', $cadet['body']);
        $this->assertTrue($cadet['can_send']);

        $this->assertSame(0, WhatsAppMessage::query()->whereNot('status', 'draft')->count());
    }

    public function test_club_channel_gets_one_summary_with_the_images(): void
    {
        app(ImageService::class)->generate($this->schedule);
        WhatsAppDestination::query()->create(['scope' => 'club', 'label' => 'Canal del club', 'provider' => 'manual', 'destination_type' => 'channel', 'invite_url' => 'https://whatsapp.com/channel/abc', 'include_images' => true, 'is_active' => true]);

        $summary = collect($this->preview())->firstWhere('is_summary', true);

        $this->assertStringContainsString('⚽ Partits de la jornada · Jornada del 17/10/2026 al 18/10/2026', $summary['body']);
        $this->assertStringContainsString("📅 DISSABTE\n🏠 11:15 · Aleví A - CF Olot\n🏠 17:00 · Primer equip - UE Llagostera", $summary['body']);
        $this->assertStringContainsString("📅 DIUMENGE\n🚌 10:00 · Cadet A - UE Figueres (Figueres)", $summary['body']);
        $this->assertCount(2, $summary['attachments']);
        $this->assertSame('https://whatsapp.com/channel/abc', $summary['share_url']);
    }

    public function test_preview_does_not_duplicate_and_keeps_edited_drafts(): void
    {
        $this->destination('alevi-a');
        $id = collect($this->preview())->firstWhere('team', 'Aleví A')['id'];

        $this->spa()->putJson("/api/whatsapp-messages/{$id}", ['body' => 'Text editat pel gestor'])->assertOk()->assertJsonPath('data.body', 'Text editat pel gestor');
        $again = collect($this->preview());

        $this->assertSame(1, WhatsAppMessage::query()->where('whatsapp_destination_id', WhatsAppDestination::query()->value('id'))->count());
        $this->assertSame('Text editat pel gestor', $again->firstWhere('id', $id)['body']);
    }

    public function test_sending_requires_explicit_confirmation(): void
    {
        $this->destination('cadet-a', 'log', 'phone', '+34600000000');
        $id = collect($this->preview())->firstWhere('team', 'Cadet A')['id'];

        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/send", ['message_ids' => [$id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.confirm.0', 'Cal confirmar l\'enviament.');

        $this->assertSame('draft', WhatsAppMessage::query()->find($id)->status->value);
    }

    public function test_message_is_sent_once_and_never_duplicated(): void
    {
        $this->destination('cadet-a', 'log', 'phone', '+34600000000');
        $id = collect($this->preview())->firstWhere('team', 'Cadet A')['id'];

        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/send", ['message_ids' => [$id], 'confirm' => true])
            ->assertOk()->assertJsonPath('data.queued', [$id]);

        $message = WhatsAppMessage::query()->find($id);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->sent_at);
        $this->assertStringStartsWith('sim-', $message->provider_message_id);

        // Segon clic, una altra pestanya o una nova previsualització: no s'envia res més.
        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/send", ['message_ids' => [$id], 'confirm' => true])
            ->assertOk()->assertJsonPath('data.queued', [])->assertJsonPath('data.skipped', [$id]);
        $this->preview();

        $this->assertSame(1, WhatsAppMessage::query()->count());
        $this->assertSame(1, $message->refresh()->attempts);
        $this->assertDatabaseHas('audit_logs', ['event' => 'whatsapp.sent', 'auditable_id' => $id]);
    }

    public function test_manual_messages_are_marked_as_sent_by_the_manager(): void
    {
        $this->destination('alevi-a');
        $id = collect($this->preview())->firstWhere('team', 'Aleví A')['id'];

        // El proveïdor manual no s'envia des del servidor.
        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/send", ['message_ids' => [$id], 'confirm' => true])->assertJsonPath('data.queued', []);

        $this->spa()->postJson("/api/whatsapp-messages/{$id}/mark-sent")->assertOk()->assertJsonPath('data.status', 'manual_sent');
        $this->spa()->postJson("/api/whatsapp-messages/{$id}/mark-sent")->assertUnprocessable();
    }

    public function test_changes_after_sending_produce_an_update_and_mark_the_old_message_outdated(): void
    {
        $this->destination('alevi-a');
        $id = collect($this->preview())->firstWhere('team', 'Aleví A')['id'];
        $this->spa()->postJson("/api/whatsapp-messages/{$id}/mark-sent")->assertOk();

        // Canvi d'horari i nova confirmació.
        $service = app(ScheduleService::class);
        $alevi = GameMatch::query()->where('team_id', $this->team('alevi-a')->id)->sole();
        $service->move($this->schedule, $alevi, ['match_date' => '2026-10-17', 'kickoff_time' => '12:00', 'field_area_id' => $this->area('camp_2')->id], $this->schedule->refresh()->lock_version);
        $service->confirm($this->schedule, $this->schedule->refresh()->lock_version);

        $messages = collect($this->preview())->where('team', 'Aleví A');

        $this->assertSame('outdated', WhatsAppMessage::query()->find($id)->status->value);
        $new = $messages->firstWhere('status', 'draft');
        $this->assertStringContainsString('🕒 Partit: 12:00', $new['body']);
    }

    public function test_failed_messages_can_be_retried_but_unknown_ones_are_not_retried_automatically(): void
    {
        $this->useGateway();
        $this->destination('alevi-a', 'gateway', 'group', '120363000000000001@g.us');
        $this->destination('cadet-a', 'gateway', 'group', '120363000000000002@g.us');
        $ids = collect($this->preview())->pluck('id', 'team');

        // Respostes de la passarel·la, per ordre: error 500, temps esgotat, èxit.
        $responses = ['error', 'timeout', 'ok'];
        Http::fake(['passarella.example.org/*' => function () use (&$responses) {
            return match (array_shift($responses)) {
                'error' => Http::response(['error' => 'down'], 500),
                'timeout' => throw new ConnectionException('timeout'),
                default => Http::response(['id' => 'ok-1'], 200),
            };
        }]);

        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/send", ['message_ids' => [$ids['Aleví A']], 'confirm' => true])->assertOk();
        $this->assertSame('failed', WhatsAppMessage::query()->find($ids['Aleví A'])->status->value);

        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/send", ['message_ids' => [$ids['Cadet A']], 'confirm' => true])->assertOk();
        $this->assertSame('unknown', WhatsAppMessage::query()->find($ids['Cadet A'])->status->value);

        // Només es reintenta el que ha fallat; el d'estat desconegut no (podria haver arribat).
        $this->spa()->postJson("/api/schedules/{$this->schedule->id}/whatsapp/retry-failed")->assertOk()->assertJsonPath('data.queued', [$ids['Aleví A']]);

        $this->assertSame('sent', WhatsAppMessage::query()->find($ids['Aleví A'])->status->value);
        $this->assertSame('unknown', WhatsAppMessage::query()->find($ids['Cadet A'])->status->value);
        $this->assertSame(2, WhatsAppMessage::query()->find($ids['Aleví A'])->attempts);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token') && $request['to'] === '120363000000000001@g.us');
    }

    public function test_provider_responses_and_destination_ids_do_not_leak(): void
    {
        $destination = $this->destination('cadet-a', 'log', 'phone', '+34600001234');

        $this->assertNotSame('+34600001234', \DB::table('whatsapp_destinations')->where('id', $destination->id)->value('external_id'));
        $this->actingAs($this->admin(), 'web')->spa()->getJson('/api/whatsapp-destinations')
            ->assertOk()
            ->assertJsonPath('data.0.external_id_hint', '…1234')
            ->assertJsonMissingPath('data.0.external_id');
    }

    public function test_manager_cannot_change_templates_or_destinations(): void
    {
        $this->spa()->postJson('/api/whatsapp-templates', ['name' => 'X', 'kind' => 'match', 'applies_to' => 'any', 'body' => 'Hola', 'is_active' => true])->assertForbidden();
        $this->spa()->postJson('/api/whatsapp-destinations', ['scope' => 'club', 'label' => 'X', 'provider' => 'manual', 'destination_type' => 'channel', 'is_active' => true])->assertForbidden();
        $this->spa()->putJson('/api/settings', ['club_name' => 'X', 'primary_color' => '#000000', 'primary_dark_color' => '#000000'])->assertForbidden();
    }

    public function test_templates_with_unknown_variables_are_rejected(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/whatsapp-templates', [
            'name' => 'Prova', 'kind' => 'match', 'applies_to' => 'home', 'body' => 'Hola {{equip}} {{contrasenya}}', 'is_active' => true,
        ])->assertUnprocessable()->assertJsonPath('errors.body.0', 'La plantilla fa servir variables que no existeixen: contrasenya.');
    }

    public function test_destinations_are_validated_against_the_provider(): void
    {
        $this->actingAs($this->admin(), 'web');
        $base = ['scope' => 'team', 'team_id' => $this->team('alevi-a')->id, 'label' => 'Grup', 'is_active' => true];

        $this->spa()->postJson('/api/whatsapp-destinations', [...$base, 'provider' => 'gateway', 'destination_type' => 'group', 'external_id' => '1@g.us'])
            ->assertUnprocessable()->assertJsonPath('errors.provider.0', 'Aquest proveïdor no està activat al servidor.');

        $this->useGateway();
        $this->spa()->postJson('/api/whatsapp-destinations', [...$base, 'provider' => 'gateway', 'destination_type' => 'group', 'external_id' => 'grup-equip'])
            ->assertUnprocessable()->assertJsonPath('errors.external_id.0', 'L\'identificador d\'un grup ha de ser de la forma 120363000000000000@g.us.');

        $this->spa()->postJson('/api/whatsapp-destinations', [...$base, 'provider' => 'manual', 'destination_type' => 'channel'])
            ->assertUnprocessable()->assertJsonPath('errors.destination_type.0', 'Els canals són destins del club, no d\'un equip.');

        $this->spa()->postJson('/api/whatsapp-destinations', [...$base, 'provider' => 'manual', 'destination_type' => 'group'])->assertCreated();
    }
}
