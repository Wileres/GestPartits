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
}
