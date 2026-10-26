<?php

namespace Tests\Feature\Matches;

use App\Models\GameMatch;
use App\Models\Schedule;
use App\Support\ClubSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\Feature\Communications\ConfirmsSchedule;
use Tests\TestCase;

/**
 * Resultat d'un partit acabat (gols de l'equip i del rival).
 */
class MatchResultTest extends TestCase
{
    use ConfirmsSchedule, RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        $this->actingAs($this->manager(), 'web');
    }

    /**
     * Dades d'un partit tal com les envia la fitxa, amb el resultat afegit.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload($match, array $extra = []): array
    {
        return [
            'team_id' => $match->team_id, 'status' => $match->status->value, 'home_away' => $match->home_away->value, 'opponent' => $match->opponent,
            'match_date' => $match->match_date->toDateString(), 'kickoff_time' => substr($match->kickoff_time, 0, 5),
            'call_time' => $match->call_time === null ? null : substr($match->call_time, 0, 5),
            'field_area_id' => $match->field_area_id, 'venue_name' => $match->venue_name, 'address' => $match->address, 'municipality' => $match->municipality,
            'lock_version' => $match->refresh()->lock_version, ...$extra,
        ];
    }

    public function test_a_result_is_saved_and_exposed(): void
    {
        $matches = $this->matchesFor($this->weekend());

        $this->spa()->putJson("/api/matches/{$matches['alevi']->id}", $this->payload($matches['alevi'], ['score_team' => 3, 'score_opponent' => 1]))
            ->assertOk()
            ->assertJsonPath('data.score_team', 3)
            ->assertJsonPath('data.score_opponent', 1)
            ->assertJsonPath('data.has_result', true);

        // Els altres partits de la jornada no en porten.
        $flags = $this->spa()->getJson("/api/matchdays/{$this->weekend()->id}/matches")->assertOk()->json('data.*.has_result');
        $this->assertSame([true, false, false], collect($flags)->sortDesc()->values()->all());
    }

    public function test_a_result_can_be_cleared(): void
    {
        $match = $this->matchesFor($this->weekend())['alevi'];
        $this->spa()->putJson("/api/matches/{$match->id}", $this->payload($match, ['score_team' => 0, 'score_opponent' => 0]))->assertOk()->assertJsonPath('data.has_result', true);

        $this->spa()->putJson("/api/matches/{$match->id}", $this->payload($match->refresh(), ['score_team' => null, 'score_opponent' => null]))
            ->assertOk()->assertJsonPath('data.has_result', false);
    }

    public function test_both_scores_are_required_and_must_be_sensible(): void
    {
        $match = $this->matchesFor($this->weekend())['alevi'];

        $this->spa()->putJson("/api/matches/{$match->id}", $this->payload($match, ['score_team' => 2]))
            ->assertUnprocessable()->assertJsonPath('errors.score_opponent.0', 'Indica els gols dels dos equips.');

        $this->spa()->putJson("/api/matches/{$match->id}", $this->payload($match, ['score_team' => -1, 'score_opponent' => 100]))
            ->assertUnprocessable()->assertJsonValidationErrors(['score_team', 'score_opponent']);
    }

    public function test_a_cancelled_match_cannot_have_a_result(): void
    {
        $match = $this->matchesFor($this->weekend())['alevi'];

        $this->spa()->putJson("/api/matches/{$match->id}", $this->payload($match, ['status' => 'cancelled', 'score_team' => 1, 'score_opponent' => 0]))
            ->assertUnprocessable()->assertJsonPath('errors.score_team.0', 'Un partit cancel·lat no pot tenir resultat.');
    }

    public function test_saving_only_the_result_does_not_put_a_confirmed_schedule_back_to_review(): void
    {
        $matchday = $this->weekend();
        $matches = $this->matchesFor($matchday);
        // Els partits a casa reals ja tenen la instal·lació assignada.
        GameMatch::query()->where('home_away', 'home')->update(['venue_id' => app(ClubSettings::class)->homeVenue()->id]);
        $matches['alevi']->refresh();
        $schedule = $this->confirmed($matchday);
        $version = $schedule->lock_version;

        $this->spa()->putJson("/api/matches/{$matches['alevi']->id}", $this->payload($matches['alevi'], ['score_team' => 2, 'score_opponent' => 2]))->assertOk();

        $schedule = Schedule::query()->findOrFail($schedule->id);
        $this->assertSame('confirmed', $schedule->status->value);
        $this->assertSame($version, $schedule->lock_version);

        // Qualsevol altre canvi sí que la deixa pendent de revisar.
        $this->spa()->putJson("/api/matches/{$matches['alevi']->id}", $this->payload($matches['alevi']->refresh(), ['kickoff_time' => '12:00', 'call_time' => null, 'score_team' => 2, 'score_opponent' => 2]))->assertOk();
        $this->assertSame('needs_review', Schedule::query()->findOrFail($schedule->id)->status->value);
    }

    public function test_editing_unrelated_fields_does_not_lock_an_automatically_placed_match(): void
    {
        $match = $this->matchesFor($this->weekend())['alevi'];
        $match->update(['is_locked' => false, 'placement_source' => 'auto', 'venue_id' => app(ClubSettings::class)->homeVenue()->id]);

        $this->spa()->putJson("/api/matches/{$match->id}", $this->payload($match->refresh(), ['notes' => 'Porteu aigua']))
            ->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.placement_source', 'auto');
    }
}
