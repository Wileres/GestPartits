<?php

namespace Tests\Feature\Communications;

use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterPage;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Infrastructure\Imaging\ChromiumRenderer;
use App\Infrastructure\Imaging\FakeRenderer;
use App\Models\GameMatch;
use App\Models\GeneratedImage;
use App\Models\ScheduleVersion;
use App\Services\ImageService;
use App\Services\Scheduling\ScheduleService;
use App\Support\ClubBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Cas 21: es generen correctament les imatges de dissabte i diumenge.
 */
class ImageGenerationTest extends TestCase
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

    public function test_images_require_a_confirmed_schedule(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = app(ScheduleService::class)->forMatchday($matchday);

        $this->spa()->postJson("/api/schedules/{$schedule->id}/images")
            ->assertUnprocessable()
            ->assertJsonPath('errors.schedule.0', 'Cal confirmar la programació abans de generar les imatges i enviar els avisos.');
    }

    public function test_saturday_and_sunday_images_are_generated_at_1080x1920(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);

        $response = $this->spa()->postJson("/api/schedules/{$schedule->id}/images")->assertStatus(202);

        // A les proves la cua és síncrona: en acabar la petició les imatges ja hi són.
        $images = $this->spa()->getJson("/api/schedules/{$schedule->id}/images")->assertOk()
            ->assertJsonPath('data.generating', false)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.day', '2026-10-17')
            ->assertJsonPath('data.images.0.weekday', 'dissabte')
            ->assertJsonPath('data.images.1.day', '2026-10-18')
            ->assertJsonPath('data.images.1.width', 1080)
            ->assertJsonPath('data.images.1.height', 1920)
            ->json('data.images');

        unset($response);
        foreach (GeneratedImage::all() as $image) {
            Storage::disk('local')->assertExists($image->path);
            $size = getimagesizefromstring(Storage::disk('local')->get($image->path));
            $this->assertSame([1080, 1920], [$size[0], $size[1]]);
            $this->assertSame($schedule->confirmed_version_id, $image->schedule_version_id);
        }

        // Dissabte: Aleví (casa) i Primer equip (casa) en ordre cronològic. Diumenge: Cadet (a fora, amb el lloc).
        [$saturday, $sunday] = $this->renderer->rendered;
        $this->assertSame(['Dissabte', '17/10/2026'], [$saturday->poster->title, $saturday->poster->dateLabel]);
        $this->assertSame(['Aleví A', 'Primer equip'], array_map(fn ($e) => $e->team, $saturday->entries));
        $this->assertSame('10:30', $saturday->entries[0]->callTime);
        $this->assertSame(['Diumenge', false, 'Camp Municipal Vilatenim · Figueres'], [$sunday->poster->title, $sunday->entries[0]->home, $sunday->entries[0]->place]);

        $this->get($images[0]['url'])->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_regenerating_replaces_the_images_of_the_same_version(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);

        app(ImageService::class)->generate($schedule);
        app(ImageService::class)->generate($schedule);

        $this->assertSame(2, GeneratedImage::query()->count());
        $this->assertCount(2, Storage::disk('local')->allFiles('images'));
    }

    public function test_crowded_days_are_split_into_pages(): void
    {
        $matchday = $this->weekend();
        $team = $this->team('alevi-a');
        foreach (range(0, 11) as $i) {
            GameMatch::query()->create([
                'matchday_id' => $matchday->id, 'team_id' => $team->id, 'opponent' => "Rival {$i}", 'home_away' => HomeAway::Away,
                'status' => MatchStatus::Confirmed, 'game_format' => 'f7', 'duration_min' => 60, 'match_date' => '2026-10-17',
                'kickoff_time' => sprintf('%02d:00', 9 + $i), 'venue_name' => 'Camp', 'municipality' => 'Olot',
            ]);
        }
        $schedule = app(ScheduleService::class)->forMatchday($matchday);
        // L'equip duplicat és un conflicte crític: es confirma directament per provar la paginació.
        $schedule->forceFill(['status' => 'confirmed', 'confirmed_version_id' => ScheduleVersion::query()->create([
            'schedule_id' => $schedule->id, 'number' => 1, 'kind' => 'confirmation', 'snapshot' => [],
        ])->id])->save();

        app(ImageService::class)->generate($schedule->refresh());

        $this->assertSame([1, 2], GeneratedImage::query()->orderBy('page')->pluck('page')->all());
        $this->assertSame(PosterPage::MODE_COMPACT, $this->renderer->rendered[0]->mode);
        $this->assertStringContainsString('2 pàgines', $this->spa()->getJson("/api/schedules/{$schedule->id}/images")->json('data.images.0.warnings.0'));
    }

    public function test_images_are_not_public(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        $image = app(ImageService::class)->generate($schedule)->first();

        $this->app['auth']->forgetGuards();
        auth('web')->logout();

        $this->getJson("/api/generated-images/{$image->id}")->assertUnauthorized();
    }

    public function test_poster_footer_shows_the_club_account_and_hashtag(): void
    {
        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        $poster = app(ImageService::class)->posters($schedule->load('matchday'))[0];
        $page = (new LayoutPlanner)->plan($poster)->pages[0];

        $html = (new ChromiumRenderer(app('view'), '/usr/bin/chromium'))->html($page, app(ClubBranding::class)->forImages());

        $this->assertMatchesRegularExpression('/<footer>\s*<span>@olotjspm<\/span>.*<span>#futboldebarri<\/span>\s*<\/footer>/s', $html);
        $this->assertStringNotContainsString('Som-hi, Joventut!', $html);
    }

    #[Group('chromium')]
    public function test_chromium_renders_a_real_1080x1920_image(): void
    {
        $binary = config('gestpartits.images.chromium_binary');
        if (! is_file($binary)) {
            $this->markTestSkipped('Chromium no està instal·lat.');
        }

        $matchday = $this->weekend();
        $this->matchesFor($matchday);
        $schedule = $this->confirmed($matchday);
        $poster = app(ImageService::class)->posters($schedule->load('matchday'))[0];
        $page = (new LayoutPlanner)->plan($poster)->pages[0];

        $png = (new ChromiumRenderer(app('view'), $binary))->render($page, app(ClubBranding::class)->forImages());

        $size = getimagesizefromstring($png);
        $this->assertSame([1080, 1920, 'image/png'], [$size[0], $size[1], $size['mime']]);
        $this->assertGreaterThan(20000, strlen($png), 'La imatge té contingut');
    }
}
