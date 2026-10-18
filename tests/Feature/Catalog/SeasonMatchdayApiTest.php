<?php

namespace Tests\Feature\Catalog;

use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class SeasonMatchdayApiTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
    }

    public function test_only_one_season_is_current(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->postJson('/api/seasons', [
            'name' => '2027-2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30', 'is_current' => true,
        ])->assertCreated()->assertJsonPath('data.is_current', true);

        $this->assertSame(['2027-2028'], Season::query()->where('is_current', true)->pluck('name')->all());
    }

    public function test_season_name_format_and_dates_are_validated(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->postJson('/api/seasons', [
            'name' => '26/27', 'starts_on' => '2027-06-30', 'ends_on' => '2026-09-01', 'is_current' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'El nom ha de tenir el format 2026-2027.')
            ->assertJsonPath('errors.ends_on.0', 'El camp data de final ha de ser una data posterior a data d\'inici.');
    }

    public function test_matchday_is_created_with_a_default_label_in_dd_mm_yyyy(): void
    {
        $season = Season::query()->where('is_current', true)->sole();

        $this->actingAs($this->manager(), 'web')->spa()->postJson("/api/seasons/{$season->id}/matchdays", [
            'starts_on' => '2026-10-31', 'ends_on' => '2026-11-01',
        ])->assertCreated()->assertJsonPath('data.display_label', 'Jornada del 31/10/2026 al 01/11/2026');
    }

    public function test_matchday_must_be_inside_the_season_and_unique(): void
    {
        $season = Season::query()->where('is_current', true)->sole();
        $this->actingAs($this->manager(), 'web');

        $this->spa()->postJson("/api/seasons/{$season->id}/matchdays", ['starts_on' => '2027-08-07', 'ends_on' => '2027-08-08'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_on.0', 'La jornada ha d\'estar dins de la temporada.');

        $this->spa()->postJson("/api/seasons/{$season->id}/matchdays", ['starts_on' => '2026-10-17', 'ends_on' => '2026-10-18'])->assertCreated();
        $this->spa()->postJson("/api/seasons/{$season->id}/matchdays", ['starts_on' => '2026-10-17', 'ends_on' => '2026-10-18'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_on.0', 'Ja hi ha una jornada que comença aquest dia.');

        $this->spa()->postJson("/api/seasons/{$season->id}/matchdays", ['starts_on' => '2026-10-24', 'ends_on' => '2026-10-30'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ends_on.0', 'Una jornada pot durar com a màxim 4 dies.');
    }

    public function test_matchday_with_matches_cannot_be_deleted(): void
    {
        $matchday = $this->weekend();
        $this->actingAs($this->manager(), 'web')->spa()->postJson("/api/matchdays/{$matchday->id}/matches", [
            'team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home',
        ])->assertCreated();

        $this->spa()->deleteJson("/api/matchdays/{$matchday->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.matchday.0', 'No es pot esborrar una jornada que té partits.');
    }
}
