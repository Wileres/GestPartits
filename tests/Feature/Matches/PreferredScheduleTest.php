<?php

namespace Tests\Feature\Matches;

use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Un partit a casa nou carrega l'horari preferent de l'equip i la convocatòria és una hora abans.
 */
class PreferredScheduleTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        $this->actingAs($this->manager(), 'web');
    }

    private function create(string $team, array $data = [], ?Matchday $matchday = null): TestResponse
    {
        return $this->spa()->postJson('/api/matchdays/'.($matchday ?? $this->weekend())->id.'/matches', [
            'team_id' => $this->team($team)->id, 'home_away' => 'home', 'opponent' => 'CF Olot', ...$data,
        ]);
    }

    public function test_primer_equip_loads_saturday_17_on_the_full_field(): void
    {
        $this->create('primer-equip')->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.match_date', '2026-10-17')
            ->assertJsonPath('data.kickoff_time', '17:00')
            ->assertJsonPath('data.field_area.code', 'full')
            ->assertJsonPath('data.placement_source', 'auto')
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_benjami_teams_load_their_day_time_and_field(): void
    {
        $this->create('benjami-a')->assertJsonPath('data.match_date', '2026-10-18')->assertJsonPath('data.kickoff_time', '12:30')->assertJsonPath('data.field_area.code', 'camp_1');
        $this->create('benjami-b')->assertJsonPath('data.match_date', '2026-10-18')->assertJsonPath('data.kickoff_time', '12:30')->assertJsonPath('data.field_area.code', 'camp_2');
        $this->create('benjami-c')->assertJsonPath('data.match_date', '2026-10-17')->assertJsonPath('data.kickoff_time', '12:30');
    }

    public function test_the_category_rule_is_inherited_and_two_teams_use_different_fields(): void
    {
        // Aleví: regla de categoria (dissabte 11:15) sense camp preferent: el segon equip va al camp lliure.
        $this->spa()->postJson('/api/teams', ['category_id' => $this->team('alevi-a')->category_id, 'name' => 'Aleví B', 'is_active' => true])->assertCreated();

        $first = $this->create('alevi-a')->assertJsonPath('data.kickoff_time', '11:15')->assertJsonPath('data.match_date', '2026-10-17');
        $second = $this->spa()->postJson("/api/matchdays/{$this->weekend()->id}/matches", [
            'team_id' => Team::query()->where('name', 'Aleví B')->value('id'), 'home_away' => 'home',
        ])->assertCreated()->assertJsonPath('data.kickoff_time', '11:15');

        $this->assertSame(['camp_1', 'camp_2'], [$first->json('data.field_area.code'), $second->json('data.field_area.code')]);
    }

    public function test_call_time_defaults_to_one_hour_before_the_match(): void
    {
        $this->create('alevi-a')->assertJsonPath('data.kickoff_time', '11:15')->assertJsonPath('data.call_time', '10:15');
        $this->create('primer-equip')->assertJsonPath('data.call_time', '16:00');
    }

    public function test_an_explicit_call_time_and_an_explicit_schedule_are_respected(): void
    {
        $this->create('alevi-a', ['call_time' => '10:00'])->assertJsonPath('data.call_time', '10:00')->assertJsonPath('data.kickoff_time', '11:15');

        $this->create('primer-equip', ['match_date' => '2026-10-18', 'kickoff_time' => '10:30', 'field_area_id' => $this->area('full')->id])
            ->assertJsonPath('data.match_date', '2026-10-18')
            ->assertJsonPath('data.kickoff_time', '10:30')
            ->assertJsonPath('data.call_time', '09:30')
            ->assertJsonPath('data.placement_source', 'manual')
            ->assertJsonPath('data.is_locked', true);
    }

    public function test_teams_without_a_preferred_schedule_and_away_or_cancelled_matches_are_left_unscheduled(): void
    {
        // Escoleta només té una finestra horària (no abans de les 10:00), no una hora preferent.
        $this->create('escoleta-a')->assertCreated()->assertJsonPath('data.kickoff_time', null)->assertJsonPath('data.call_time', null)->assertJsonPath('data.field_area_id', null);
        $this->create('primer-equip', ['home_away' => 'away'])->assertJsonPath('data.kickoff_time', null)->assertJsonPath('data.field_area_id', null);
        $this->create('benjami-a', ['status' => 'cancelled'])->assertJsonPath('data.kickoff_time', null);
    }

    public function test_a_matchday_without_the_preferred_weekday_stays_unscheduled(): void
    {
        $season = Season::query()->where('is_current', true)->sole();
        $saturdayOnly = Matchday::query()->create(['season_id' => $season->id, 'starts_on' => '2026-10-24', 'ends_on' => '2026-10-24']);

        // Benjamí A prefereix el diumenge, però aquesta jornada només té dissabte.
        $this->create('benjami-a', [], $saturdayOnly)->assertCreated()->assertJsonPath('data.kickoff_time', null);
        $this->create('primer-equip', [], $saturdayOnly)->assertJsonPath('data.kickoff_time', '17:00');
    }

    public function test_quick_entry_loads_the_preferences_too(): void
    {
        $matchday = $this->weekend();

        $this->spa()->postJson("/api/matchdays/{$matchday->id}/matches/bulk", ['rows' => [
            ['team_id' => $this->team('primer-equip')->id, 'home_away' => 'home', 'opponent' => 'UE Llagostera'],
            ['team_id' => $this->team('cadet-a')->id, 'home_away' => 'away', 'opponent' => 'UE Figueres'],
        ]])->assertOk()->assertJsonPath('data.0.kickoff_time', '17:00')->assertJsonPath('data.0.status', 'confirmed')->assertJsonPath('data.1.kickoff_time', null);
    }

    public function test_giving_a_time_later_proposes_the_default_call_time_only_once(): void
    {
        $id = $this->create('escoleta-a')->json('data.id');
        $match = GameMatch::query()->findOrFail($id);
        $payload = ['team_id' => $match->team_id, 'home_away' => 'home', 'opponent' => 'CF Olot', 'lock_version' => 0];

        $this->spa()->putJson("/api/matches/{$id}", [...$payload, 'match_date' => '2026-10-17', 'kickoff_time' => '10:30', 'field_area_id' => $this->area('camp_1')->id])
            ->assertOk()->assertJsonPath('data.call_time', '09:30');

        // Si el gestor canvia la convocatòria, no es torna a sobreescriure.
        $this->spa()->putJson("/api/matches/{$id}", [...$payload, 'lock_version' => 1, 'match_date' => '2026-10-17', 'kickoff_time' => '11:00', 'call_time' => '10:15', 'field_area_id' => $this->area('camp_1')->id])
            ->assertOk()->assertJsonPath('data.call_time', '10:15');
    }
}
