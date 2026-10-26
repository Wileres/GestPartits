<?php

namespace Tests\Feature\Communications;

use App\Domain\Imaging\LayoutPlanner;
use App\Enums\WhatsAppMessageStatus;
use App\Infrastructure\Imaging\FakeRenderer;
use App\Models\GeneratedImage;
use App\Models\Schedule;
use App\Models\WhatsAppDestination;
use App\Models\WhatsAppMessage;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Les imatges generades es conserven en un històric, es poden descarregar i es poden esborrar a mà.
 */
class ImageHistoryTest extends TestCase
{
    use ConfirmsSchedule, RefreshDatabase, SeedsClub;

    private FakeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        Storage::fake('local');
        $this->renderer = new FakeRenderer;
        $this->app->instance(FakeRenderer::class, $this->renderer);
        $this->actingAs($this->manager(), 'web');
    }

    public function test_every_generation_is_kept_in_the_history(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);

        $first = app(ImageService::class)->generate($schedule);
        $second = app(ImageService::class)->generate($schedule);

        $this->assertNotSame($first->first()->batch_id, $second->first()->batch_id);
        $this->assertSame(4, GeneratedImage::query()->count());
        $this->assertCount(4, Storage::disk('local')->allFiles('images'));

        $history = $this->spa()->getJson('/api/generated-images')->assertOk()->json('data');
        $this->assertCount(2, $history);
        // La més recent primer.
        $this->assertSame($second->first()->batch_id, $history[0]['batch_id']);
        $this->assertSame('schedule', $history[0]['kind']);
        $this->assertSame('Jornada del 17/10/2026 al 18/10/2026', $history[0]['matchday']['display_label']);
        $this->assertCount(2, $history[0]['images']);

        // L'estat de la programació només mostra l'última generació.
        $this->spa()->getJson("/api/schedules/{$schedule->id}/images")->assertOk()->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.batch_id', $second->first()->batch_id);
    }

    public function test_history_can_be_filtered_by_kind(): void
    {
        $matchday = $this->weekend();
        $matches = $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        $matches['alevi']->update(['score_team' => 3, 'score_opponent' => 1]);

        app(ImageService::class)->generate($schedule);
        app(ImageService::class)->generateResults($matchday);

        $this->spa()->getJson('/api/generated-images?kind=results')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'results');
        $this->spa()->getJson('/api/generated-images?kind=schedule')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'schedule');
        $this->spa()->getJson('/api/generated-images?kind=altres')->assertUnprocessable();
    }

    public function test_an_image_can_be_downloaded_and_deleted(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        $images = app(ImageService::class)->generate($schedule);
        $image = $images->first();

        $this->spa()->get("/api/generated-images/{$image->id}")->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->spa()->deleteJson("/api/generated-images/{$image->id}")->assertNoContent();

        $this->assertDatabaseMissing('generated_images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($image->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'images.deleted']);
        // L'altra imatge de la mateixa generació continua.
        $this->assertSame(1, GeneratedImage::query()->count());
    }

    public function test_a_whole_generation_can_be_deleted(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        $keep = app(ImageService::class)->generate($schedule);
        $drop = app(ImageService::class)->generate($schedule);

        $this->spa()->deleteJson('/api/generated-images/batches/'.$drop->first()->batch_id)->assertNoContent();

        $this->assertSame(2, GeneratedImage::query()->count());
        $this->assertSame($keep->first()->batch_id, GeneratedImage::query()->value('batch_id'));
        $this->spa()->deleteJson('/api/generated-images/batches/no-existeix')->assertNotFound();
    }

    public function test_an_image_attached_to_a_sent_notice_cannot_be_deleted_but_one_in_a_draft_can(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        // Les imatges s'adjunten al resum de la jornada que va al canal del club.
        WhatsAppDestination::query()->create([
            'scope' => 'club', 'team_id' => null, 'label' => 'Canal del club', 'provider' => 'manual',
            'destination_type' => 'channel', 'include_images' => true, 'is_active' => true,
        ]);
        $images = app(ImageService::class)->generate($schedule);
        $this->spa()->postJson("/api/schedules/{$schedule->id}/whatsapp/preview")->assertOk();
        $message = WhatsAppMessage::query()->firstOrFail();
        $this->assertSame(2, $message->attachments()->count());

        // En un esborrany, s'esborra i deixa de ser-hi adjunta.
        $this->spa()->deleteJson("/api/generated-images/{$images[0]->id}")->assertNoContent();
        $this->assertSame(1, $message->attachments()->count());

        // Un cop enviat, no es pot esborrar.
        $message->update(['status' => WhatsAppMessageStatus::ManualSent]);
        $this->spa()->deleteJson("/api/generated-images/{$images[1]->id}")
            ->assertUnprocessable()->assertJsonPath('errors.image.0', 'Aquesta imatge està adjunta a un avís de WhatsApp que ja s\'ha enviat i no es pot esborrar.');
        $this->assertDatabaseHas('generated_images', ['id' => $images[1]->id]);
    }

    public function test_only_the_latest_generation_is_attached_to_new_notices(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        // Les imatges s'adjunten al resum de la jornada que va al canal del club.
        WhatsAppDestination::query()->create([
            'scope' => 'club', 'team_id' => null, 'label' => 'Canal del club', 'provider' => 'manual',
            'destination_type' => 'channel', 'include_images' => true, 'is_active' => true,
        ]);
        app(ImageService::class)->generate($schedule);
        $latest = app(ImageService::class)->generate($schedule);

        $this->spa()->postJson("/api/schedules/{$schedule->id}/whatsapp/preview")->assertOk();

        $attached = WhatsAppMessage::query()->firstOrFail()->attachments()->pluck('generated_image_id')->sort()->values()->all();
        $this->assertSame($latest->pluck('id')->sort()->values()->all(), $attached);
    }

    public function test_home_and_away_matches_can_be_grouped_with_the_home_ones_first(): void
    {
        $matchday = $this->weekend();
        $matches = $this->matchesFor($matchday);
        // El diumenge: un partit a casa tard i un a fora d'hora (el cadet de la plantilla).
        $matches['alevi']->update(['match_date' => '2026-10-18', 'kickoff_time' => '12:00']);

        $this->confirmed($matchday);
        $schedule = $matchday->schedule ?? Schedule::query()->where('matchday_id', $matchday->id)->firstOrFail();

        $this->spa()->postJson("/api/schedules/{$schedule->id}/images", ['group_home_away' => true])->assertStatus(202);

        $sunday = collect($this->renderer->rendered)->first(fn ($page) => $page->poster->date === '2026-10-18');
        $this->assertTrue($sunday->poster->grouped);
        // Fora a les 10:00 i casa a les 12:00: agrupat, la casa va primer.
        $this->assertSame([true, false], array_map(fn ($e) => $e->home, $sunday->entries));
        $this->assertSame(['group_home_away' => true], GeneratedImage::query()->latest('id')->first()->options);
    }

    public function test_results_image_needs_at_least_one_result(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);

        $this->spa()->postJson("/api/matchdays/{$matchday->id}/result-images")
            ->assertUnprocessable();
        $this->assertSame(0, GeneratedImage::query()->count());
    }

    public function test_results_image_summarises_the_finished_matches_in_one_image(): void
    {
        $matchday = $this->weekend();
        $matches = $this->matchesFor($matchday);
        $matches['alevi']->update(['score_team' => 3, 'score_opponent' => 1]);
        $matches['cadet']->update(['score_team' => 0, 'score_opponent' => 2]);
        // Sense resultat o cancel·lat: no hi surten.
        $matches['primer']->update(['score_team' => 5, 'score_opponent' => 5, 'status' => 'cancelled']);

        $this->spa()->getJson("/api/matchdays/{$matchday->id}/result-images")->assertOk()->assertJsonPath('data.results_count', 2)->assertJsonPath('data.images', []);

        $this->spa()->postJson("/api/matchdays/{$matchday->id}/result-images")->assertStatus(202);

        $this->assertCount(1, $this->renderer->renderedResults);
        $page = $this->renderer->renderedResults[0];
        $this->assertSame('Resultats', $page->poster->title);
        $this->assertSame('17/10/2026 – 18/10/2026', $page->poster->dateLabel);
        $this->assertSame([['Aleví A', 3, 1], ['Cadet A', 0, 2]], array_map(fn ($e) => [$e->team, $e->scoreTeam, $e->scoreOpponent], $page->entries));

        $image = GeneratedImage::query()->firstOrFail();
        $this->assertSame('results', $image->kind);
        $this->assertNull($image->schedule_id);
        $this->assertSame([LayoutPlanner::WIDTH, LayoutPlanner::HEIGHT], [$image->width, $image->height]);
        $this->assertSame('resultats-2026-10-17.png', $image->downloadName());

        $this->spa()->getJson("/api/matchdays/{$matchday->id}/result-images")->assertOk()->assertJsonCount(1, 'data.images')->assertJsonPath('data.images.0.kind', 'results');
    }

    public function test_history_and_deletion_require_login(): void
    {
        $this->app['auth']->forgetGuards();
        auth('web')->logout();

        $this->getJson('/api/generated-images')->assertUnauthorized();
        $this->deleteJson('/api/generated-images/1')->assertUnauthorized();
        $this->deleteJson('/api/generated-images/batches/x')->assertUnauthorized();
    }
}
