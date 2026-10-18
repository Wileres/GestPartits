<?php

namespace Tests\Feature\Schedules;

use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Events\ScheduleConfirmed;
use App\Models\FieldBlock;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Schedule;
use App\Models\User;
use App\Support\ClubSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    private User $user;

    private Matchday $matchday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        $this->user = $this->manager();
        $this->actingAs($this->user, 'web');
        $this->matchday = $this->weekend();
    }

    /**
     * Un partit a casa (pendent i sense horari) per a cada equip indicat.
     *
     * @return array<string, GameMatch>
     */
    private function homeMatches(string ...$slugs): array
    {
        $matches = [];
        foreach ($slugs as $slug) {
            $team = $this->team($slug);
            $matches[$slug] = GameMatch::query()->create([
                'matchday_id' => $this->matchday->id, 'team_id' => $team->id, 'opponent' => 'Rival '.$team->name,
                'home_away' => HomeAway::Home, 'status' => MatchStatus::Confirmed, 'game_format' => $team->effectiveGameFormat(),
                'duration_min' => $team->effectiveDurationMin(), 'post_min' => $team->effectivePostMin(),
            ]);
        }

        return $matches;
    }

    private function schedule(): TestResponse
    {
        return $this->spa()->getJson("/api/matchdays/{$this->matchday->id}/schedule")->assertOk();
    }

    private function propose(): TestResponse
    {
        $schedule = $this->schedule();

        return $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/proposal", ['version' => $schedule->json('data.version')]);
    }

    private function placed(TestResponse $response, GameMatch $match): array
    {
        $data = collect($response->json('data.matches'))->firstWhere('id', $match->id);

        return [$data['match_date'], $data['kickoff_time'], $data['field_area']['code'] ?? null];
    }

    public function test_schedule_is_created_with_the_engine_input(): void
    {
        $this->homeMatches('alevi-a');

        $this->schedule()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.input.dates', ['2026-10-17', '2026-10-18'])
            ->assertJsonPath('data.input.step_min', 15)
            ->assertJsonPath('data.input.slots.0.opens', '09:00')
            ->assertJsonPath('data.conflicts.0.code', 'HOME_MATCH_UNSCHEDULED')
            ->assertJsonPath('data.conflicts.0.message', 'Aleví A: partit a casa sense horari.')
            ->assertJsonPath('data.can_confirm', false);

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_proposal_applies_the_club_rules(): void
    {
        $m = $this->homeMatches('primer-equip', 'benjami-a', 'benjami-b', 'benjami-c', 'alevi-a', 'escoleta-a', 'escoleta-b', 'infantil-a', 'cadet-a', 'juvenil-a');

        $response = $this->propose()->assertOk();

        $this->assertSame(['2026-10-17', '17:00', 'full'], $this->placed($response, $m['primer-equip']));
        $this->assertSame(['2026-10-18', '12:30', 'camp_1'], $this->placed($response, $m['benjami-a']));
        $this->assertSame(['2026-10-18', '12:30', 'camp_2'], $this->placed($response, $m['benjami-b']));
        $this->assertSame(['2026-10-17', '12:30'], array_slice($this->placed($response, $m['benjami-c']), 0, 2));
        $this->assertSame(['2026-10-17', '11:15'], array_slice($this->placed($response, $m['alevi-a']), 0, 2));
        $this->assertGreaterThanOrEqual('10:00', $this->placed($response, $m['escoleta-a'])[1]);

        $response->assertJsonPath('data.status', 'proposed')
            ->assertJsonPath('data.proposal.unplaced', [])
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonPath('data.versions.0.kind', 'pre_proposal')
            ->assertJsonPath('data.versions.1.kind', 'proposal')
            ->assertJsonPath('data.versions.1.is_current', true);

        $this->assertSame(10, count($response->json('data.proposal.changes')));
        // Els partits segueixen «confirmats»: la proposta només en canvia l'horari.
        $this->assertSame('confirmed', $m['alevi-a']->fresh()->status->value);
        $this->assertSame('auto', $m['alevi-a']->fresh()->placement_source);
        // Convocatòria per defecte: una hora abans del partit.
        $this->assertSame('10:15:00', $m['alevi-a']->fresh()->call_time);
        $this->assertDatabaseHas('audit_logs', ['event' => 'schedule.proposed']);
    }

    public function test_proposal_can_be_run_again_without_duplicating_or_changing_anything(): void
    {
        $this->homeMatches('primer-equip', 'alevi-a', 'cadet-a');

        $first = $this->propose()->json('data.matches');
        $second = $this->propose()->assertOk();

        $this->assertSame(3, GameMatch::query()->count());
        $this->assertSame(
            collect($first)->map(fn ($x) => [$x['id'], $x['match_date'], $x['kickoff_time'], $x['field_area_id']])->all(),
            collect($second->json('data.matches'))->map(fn ($x) => [$x['id'], $x['match_date'], $x['kickoff_time'], $x['field_area_id']])->all(),
        );
        $second->assertJsonPath('data.proposal.changes', []);
    }

    public function test_stale_version_is_rejected(): void
    {
        $this->homeMatches('alevi-a');
        $schedule = $this->schedule();
        $id = $schedule->json('data.id');

        $this->spa()->postJson("/api/schedules/{$id}/proposal", ['version' => $schedule->json('data.version')])->assertOk();

        // Un segon gestor amb la versió antiga no pot sobreescriure la programació.
        $this->spa()->postJson("/api/schedules/{$id}/proposal", ['version' => $schedule->json('data.version')])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La programació ha canviat mentrestant (potser l\'ha modificat una altra persona). S\'ha recarregat: revisa-la i torna-ho a provar.');
    }

    public function test_editing_a_match_elsewhere_invalidates_the_calendar_version(): void
    {
        $m = $this->homeMatches('alevi-a');
        $version = $this->schedule()->json('data.version');

        $this->spa()->putJson("/api/matches/{$m['alevi-a']->id}", [
            'team_id' => $m['alevi-a']->team_id, 'status' => 'confirmed', 'home_away' => 'home', 'opponent' => 'Canvi', 'lock_version' => 0,
        ])->assertOk();

        $this->assertGreaterThan($version, $this->schedule()->json('data.version'));
    }

    public function test_manual_move_locks_the_match_and_regeneration_respects_it(): void
    {
        $m = $this->homeMatches('alevi-a', 'benjami-c');
        $schedule = $this->schedule();

        $moved = $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/moves", [
            'version' => $schedule->json('data.version'),
            'match_id' => $m['alevi-a']->id, 'match_date' => '2026-10-18', 'kickoff_time' => '10:00', 'field_area_id' => $this->area('camp_2')->id,
        ])->assertOk();

        $data = collect($moved->json('data.matches'))->firstWhere('id', $m['alevi-a']->id);
        $this->assertTrue($data['is_locked']);
        $this->assertSame('manual', $data['placement_source']);
        // Fora de la preferència (dissabte 11:15): avís, no error.
        $this->assertSame('RECOMMENDED_RULE_NOT_MET', collect($moved->json('data.conflicts'))->firstWhere('match_ids', [$m['alevi-a']->id])['code']);

        $response = $this->propose()->assertOk();
        $this->assertSame(['2026-10-18', '10:00', 'camp_2'], $this->placed($response, $m['alevi-a']));
        $this->assertSame(['2026-10-17', '12:30'], array_slice($this->placed($response, $m['benjami-c']), 0, 2));
    }

    public function test_move_validation_reports_conflicts_without_saving(): void
    {
        $m = $this->homeMatches('benjami-a', 'benjami-b', 'benjami-c');
        $id = $this->schedule()->json('data.id');
        $sunday = ['match_date' => '2026-10-18', 'kickoff_time' => '12:30'];

        foreach (['benjami-a' => 'camp_1', 'benjami-b' => 'camp_2'] as $slug => $area) {
            $this->spa()->postJson("/api/schedules/{$id}/moves", [
                'version' => $this->schedule()->json('data.version'), 'match_id' => $m[$slug]->id, ...$sunday, 'field_area_id' => $this->area($area)->id,
            ])->assertOk();
        }

        // Tercer partit de futbol 7 a la mateixa hora.
        $this->spa()->postJson("/api/schedules/{$id}/moves/validate", [
            'match_id' => $m['benjami-c']->id, ...$sunday, 'field_area_id' => $this->area('camp_1')->id,
        ])->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.conflicts.0.code', 'F7_CAPACITY_EXCEEDED');

        $this->assertNull($m['benjami-c']->fresh()->kickoff_time);
    }

    public function test_backend_rejects_impossible_moves(): void
    {
        $m = $this->homeMatches('primer-equip', 'alevi-a');
        $schedule = $this->schedule();
        $base = ['version' => $schedule->json('data.version'), 'match_date' => '2026-10-17'];
        $id = $schedule->json('data.id');

        $this->spa()->postJson("/api/schedules/{$id}/moves", [...$base, 'match_id' => $m['primer-equip']->id, 'kickoff_time' => '17:00', 'field_area_id' => $this->area('camp_1')->id])
            ->assertUnprocessable()->assertJsonPath('errors.field_area_id.0', 'Un partit de futbol 11 necessita el camp complet.');

        $this->spa()->postJson("/api/schedules/{$id}/moves", [...$base, 'match_id' => $m['alevi-a']->id, 'kickoff_time' => '11:10', 'field_area_id' => $this->area('camp_1')->id])
            ->assertUnprocessable()->assertJsonPath('errors.kickoff_time.0', 'L\'hora ha de ser en punt, i quart, i mitja o menys quart.');

        $this->spa()->postJson("/api/schedules/{$id}/moves", [...$base, 'match_id' => $m['alevi-a']->id, 'kickoff_time' => '21:15', 'field_area_id' => $this->area('camp_1')->id])
            ->assertUnprocessable()->assertJsonPath('errors.kickoff_time.0', 'L\'hora del partit ha d\'estar entre les 09:00 i les 21:00.');

        $this->spa()->postJson("/api/schedules/{$id}/moves", [...$base, 'match_id' => $m['alevi-a']->id, 'match_date' => '2026-10-20', 'kickoff_time' => '11:00', 'field_area_id' => $this->area('camp_1')->id])
            ->assertUnprocessable()->assertJsonPath('errors.match_date.0', 'La data ha de ser dins de la jornada.');
    }

    public function test_concurrent_moves_with_the_same_version_conflict(): void
    {
        $m = $this->homeMatches('alevi-a', 'benjami-c');
        $schedule = $this->schedule();
        $payload = fn (GameMatch $match) => [
            'version' => $schedule->json('data.version'), 'match_id' => $match->id,
            'match_date' => '2026-10-17', 'kickoff_time' => '10:00', 'field_area_id' => $this->area('camp_1')->id,
        ];

        $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/moves", $payload($m['alevi-a']))->assertOk();
        $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/moves", $payload($m['benjami-c']))->assertStatus(409);

        $this->assertNull($m['benjami-c']->fresh()->kickoff_time);
    }

    public function test_match_can_be_returned_to_unscheduled_and_locked_or_unlocked(): void
    {
        $m = $this->homeMatches('alevi-a');
        $proposed = $this->propose();
        $id = $proposed->json('data.id');

        $locked = $this->spa()->postJson("/api/schedules/{$id}/matches/{$m['alevi-a']->id}/lock", ['version' => $proposed->json('data.version')])->assertOk();
        $this->assertTrue($m['alevi-a']->fresh()->is_locked);

        $unlocked = $this->spa()->deleteJson("/api/schedules/{$id}/matches/{$m['alevi-a']->id}/lock", ['version' => $locked->json('data.version')])->assertOk();
        $this->assertFalse($m['alevi-a']->fresh()->is_locked);

        $this->spa()->postJson("/api/schedules/{$id}/moves", [
            'version' => $unlocked->json('data.version'), 'match_id' => $m['alevi-a']->id, 'match_date' => null, 'kickoff_time' => null, 'field_area_id' => null,
        ])->assertOk();

        $this->assertNull($m['alevi-a']->fresh()->kickoff_time);
        $this->assertNull($m['alevi-a']->fresh()->field_area_id);
    }

    public function test_cannot_confirm_with_critical_conflicts(): void
    {
        $m = $this->homeMatches('primer-equip', 'alevi-a');
        $schedule = $this->schedule();

        // Aleví a mig camp mentre el Primer equip juga a camp complet.
        foreach ([['primer-equip', '10:00', 'full'], ['alevi-a', '10:30', 'camp_1']] as [$slug, $time, $area]) {
            $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/moves", [
                'version' => $this->schedule()->json('data.version'), 'match_id' => $m[$slug]->id,
                'match_date' => '2026-10-17', 'kickoff_time' => $time, 'field_area_id' => $this->area($area)->id,
            ])->assertOk();
        }

        $current = $this->schedule()->assertJsonPath('data.can_confirm', false);

        $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/confirm", ['version' => $current->json('data.version')])
            ->assertStatus(423)
            ->assertJsonPath('message', 'No es pot confirmar la programació mentre hi hagi conflictes crítics.')
            ->assertJsonPath('conflicts.0.code', 'F11_OVERLAPS_F7');

        $this->assertNotSame('confirmed', Schedule::query()->first()->status->value);
    }

    public function test_cannot_confirm_with_unscheduled_home_matches(): void
    {
        $this->homeMatches('alevi-a');
        $schedule = $this->schedule();

        $this->spa()->postJson("/api/schedules/{$schedule->json('data.id')}/confirm", ['version' => $schedule->json('data.version')])
            ->assertStatus(423)
            ->assertJsonPath('conflicts.0.code', 'HOME_MATCH_UNSCHEDULED')
            ->assertJsonPath('conflicts.0.severity', 'critical');
    }

    public function test_confirmation_confirms_matches_once_and_later_changes_require_review(): void
    {
        Event::fake([ScheduleConfirmed::class]);
        $m = $this->homeMatches('primer-equip', 'alevi-a');
        $m['alevi-a']->update(['status' => MatchStatus::Proposed]);
        $proposed = $this->propose();
        $id = $proposed->json('data.id');
        $this->assertSame('proposed', $m['alevi-a']->fresh()->status->value);

        $confirmed = $this->spa()->postJson("/api/schedules/{$id}/confirm", ['version' => $proposed->json('data.version')])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmed_by', $this->user->name);

        $this->assertSame('confirmed', $m['alevi-a']->fresh()->status->value);
        Event::assertDispatchedTimes(ScheduleConfirmed::class, 1);

        // Doble clic: no crea cap versió nova ni torna a disparar l'esdeveniment.
        $versions = count($confirmed->json('data.versions'));
        $again = $this->spa()->postJson("/api/schedules/{$id}/confirm", ['version' => $confirmed->json('data.version')])->assertOk();
        $this->assertCount($versions, $again->json('data.versions'));
        Event::assertDispatchedTimes(ScheduleConfirmed::class, 1);

        // Un canvi posterior deixa la programació pendent de revisar.
        $this->spa()->postJson("/api/schedules/{$id}/moves", [
            'version' => $again->json('data.version'), 'match_id' => $m['alevi-a']->id,
            'match_date' => '2026-10-18', 'kickoff_time' => '10:00', 'field_area_id' => $this->area('camp_1')->id,
        ])->assertOk()->assertJsonPath('data.status', 'needs_review');
    }

    public function test_saving_a_draft_never_sends_messages(): void
    {
        Queue::fake();
        Bus::fake();
        $this->homeMatches('alevi-a');
        $proposed = $this->propose();

        $this->spa()->postJson("/api/schedules/{$proposed->json('data.id')}/save", ['version' => $proposed->json('data.version')])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.has_unsaved_changes', false);

        Queue::assertNothingPushed();
        Bus::assertNothingDispatched();
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_undo_discards_changes_and_then_goes_back_one_version(): void
    {
        $m = $this->homeMatches('alevi-a');
        $proposed = $this->propose();
        $id = $proposed->json('data.id');
        $this->assertSame('11:15', $this->placed($proposed, $m['alevi-a'])[1]);

        // Canvi sense desar → desfer torna a la proposta.
        $moved = $this->spa()->postJson("/api/schedules/{$id}/moves", [
            'version' => $proposed->json('data.version'), 'match_id' => $m['alevi-a']->id,
            'match_date' => '2026-10-18', 'kickoff_time' => '10:00', 'field_area_id' => $this->area('camp_1')->id,
        ])->assertOk()->assertJsonPath('data.has_unsaved_changes', true);

        $undone = $this->spa()->postJson("/api/schedules/{$id}/undo", ['version' => $moved->json('data.version')])->assertOk();
        $this->assertSame(['2026-10-17', '11:15', 'camp_1'], $this->placed($undone, $m['alevi-a']));

        // Sense canvis pendents → desfer torna a la versió anterior (abans de la proposta: sense horari).
        $again = $this->spa()->postJson("/api/schedules/{$id}/undo", ['version' => $undone->json('data.version')])->assertOk();
        $this->assertSame([null, null, null], $this->placed($again, $m['alevi-a']));
    }

    public function test_versions_can_be_compared(): void
    {
        $m = $this->homeMatches('alevi-a');
        $proposed = $this->propose();
        [$before, $after] = $proposed->json('data.versions');

        $this->spa()->getJson("/api/schedules/{$proposed->json('data.id')}/versions/{$before['id']}/compare/{$after['id']}")
            ->assertOk()
            ->assertJsonPath('data.0.match_id', $m['alevi-a']->id)
            ->assertJsonPath('data.0.team', 'Aleví A')
            ->assertJsonPath('data.0.from.kickoff_time', null)
            ->assertJsonPath('data.0.to.kickoff_time', '11:15')
            ->assertJsonPath('data.0.to.field_area', 'Camp 1');
    }

    public function test_field_blocks_and_away_matches_are_considered(): void
    {
        $m = $this->homeMatches('infantil-a');
        $cadet = $this->team('cadet-a');
        GameMatch::query()->create([
            'matchday_id' => $this->matchday->id, 'team_id' => $cadet->id, 'home_away' => HomeAway::Away, 'status' => MatchStatus::Confirmed,
            'game_format' => 'f11', 'duration_min' => 85, 'post_min' => 10, 'match_date' => '2026-10-18', 'kickoff_time' => '09:00', 'venue_name' => 'Vilatenim',
        ]);
        FieldBlock::query()->create([
            'venue_id' => app(ClubSettings::class)->homeVenue()->id, 'starts_at' => '2026-10-17 00:00:00', 'ends_at' => '2026-10-18 00:00:00', 'reason' => 'Festa major',
        ]);

        // Dissabte bloquejat: l'Infantil va diumenge a les 09:00 (el partit a fora del Cadet no ocupa el camp).
        $this->assertSame(['2026-10-18', '09:00', 'full'], $this->placed($this->propose()->assertOk(), $m['infantil-a']));
    }

    public function test_guest_cannot_access_the_schedule(): void
    {
        $this->app['auth']->forgetGuards();

        $this->spa()->getJson("/api/matchdays/{$this->matchday->id}/schedule")->assertUnauthorized();
    }
}
