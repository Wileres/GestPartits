<?php

namespace Tests\Feature\Matches;

use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class QuickEntryTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        $this->actingAs($this->manager(), 'web');
    }

    public function test_creates_the_weekend_matches_in_one_go(): void
    {
        $matchday = $this->weekend();

        $this->spa()->postJson("/api/matchdays/{$matchday->id}/matches/bulk", ['rows' => [
            ['team_id' => $this->team('escoleta-a')->id, 'status' => 'confirmed', 'home_away' => 'home', 'opponent' => 'CE Besalú'],
            ['team_id' => $this->team('cadet-a')->id, 'status' => 'confirmed', 'home_away' => 'away', 'opponent' => 'UE Figueres', 'round_label' => 'Jornada 5'],
            ['team_id' => $this->team('juvenil-a')->id, 'home_away' => 'home', 'opponent' => 'UE Llagostera'],
        ]])->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(3, GameMatch::query()->where('matchday_id', $matchday->id)->count());
        $this->assertSame('Jornada 5', GameMatch::query()->where('team_id', $this->team('cadet-a')->id)->value('round_label'));
    }

    public function test_does_not_duplicate_teams_that_already_have_a_match(): void
    {
        $matchday = $this->weekend();
        $team = $this->team('escoleta-a');
        $row = ['team_id' => $team->id, 'status' => 'confirmed', 'home_away' => 'home'];

        $this->spa()->postJson("/api/matchdays/{$matchday->id}/matches/bulk", ['rows' => [$row]])->assertOk();

        $errors = $this->spa()->postJson("/api/matchdays/{$matchday->id}/matches/bulk", ['rows' => [
            ['team_id' => $this->team('escoleta-b')->id, 'status' => 'confirmed', 'home_away' => 'home'],
            $row,
        ]])->assertUnprocessable()->json('errors');

        $this->assertSame(['Aquest equip ja té un partit en aquesta jornada.'], $errors['rows.1.team_id']);

        // Tot o res: Escoleta B tampoc no s'ha creat.
        $this->assertSame(1, GameMatch::query()->where('matchday_id', $matchday->id)->count());
    }

    public function test_away_rows_take_a_free_day_and_time_and_get_the_default_call_time(): void
    {
        $matchday = $this->weekend();

        $this->spa()->postJson("/api/matchdays/{$matchday->id}/matches/bulk", ['rows' => [
            ['team_id' => $this->team('cadet-a')->id, 'home_away' => 'away', 'opponent' => 'UE Figueres', 'match_date' => '2026-10-18', 'kickoff_time' => '10:10'],
        ]])->assertOk()
            ->assertJsonPath('data.0.match_date', '2026-10-18')
            ->assertJsonPath('data.0.kickoff_time', '10:10')
            ->assertJsonPath('data.0.call_time', '09:10')
            ->assertJsonPath('data.0.field_area_id', null);
    }

    public function test_home_rows_cannot_carry_a_day_or_time_and_dates_must_be_in_the_matchday(): void
    {
        $matchday = $this->weekend();

        $errors = $this->spa()->postJson("/api/matchdays/{$matchday->id}/matches/bulk", ['rows' => [
            ['team_id' => $this->team('escoleta-a')->id, 'home_away' => 'home', 'match_date' => '2026-10-17', 'kickoff_time' => '11:00'],
            ['team_id' => $this->team('cadet-a')->id, 'home_away' => 'away', 'match_date' => '2026-10-30', 'kickoff_time' => '11:00'],
            ['team_id' => $this->team('juvenil-a')->id, 'home_away' => 'away', 'kickoff_time' => '11:00'],
        ]])->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('rows.0.match_date', $errors);
        $this->assertSame(['La data ha de ser dins de la jornada.'], $errors['rows.1.match_date']);
        $this->assertSame(["Cal indicar la data si s'indica l'hora."], $errors['rows.2.match_date']);
        $this->assertSame(0, GameMatch::query()->where('matchday_id', $matchday->id)->count());
    }
}
