<?php

namespace Tests\Feature\Matches;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class MatchApiTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        $this->user = $this->manager();
        $this->actingAs($this->user, 'web');
    }

    private function create(array $data): TestResponse
    {
        return $this->spa()->postJson("/api/matchdays/{$this->weekend()->id}/matches", $data);
    }

    public function test_new_matches_are_confirmed_by_default(): void
    {
        $this->create(['team_id' => $this->team('cadet-a')->id, 'home_away' => 'away', 'opponent' => 'UE Figueres'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.game_format', 'f11')
            ->assertJsonPath('data.duration_min', 85)
            ->assertJsonPath('data.post_min', 10)
            ->assertJsonPath('data.team.name', 'Cadet A')
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_only_proposed_confirmed_and_cancelled_statuses_exist(): void
    {
        foreach (['proposed', 'confirmed', 'cancelled'] as $status) {
            $this->create(['team_id' => $this->team('cadet-a')->id, 'home_away' => 'away', 'status' => $status])->assertCreated()->assertJsonPath('data.status', $status);
        }

        foreach (['pending', 'postponed', 'rest'] as $status) {
            $this->create(['team_id' => $this->team('cadet-a')->id, 'home_away' => 'away', 'status' => $status])
                ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        }
    }

    public function test_manual_home_placement_is_locked_and_reports_end_times(): void
    {
        $this->create([
            'team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home', 'opponent' => 'CF Olot',
            'match_date' => '2026-10-17', 'kickoff_time' => '11:15', 'call_time' => '10:30', 'field_area_id' => $this->area('camp_1')->id,
        ])->assertCreated()
            ->assertJsonPath('data.weekday', 6)
            ->assertJsonPath('data.end_time', '12:15')
            ->assertJsonPath('data.occupation_end_time', '12:25')
            ->assertJsonPath('data.field_area.code', 'camp_1')
            ->assertJsonPath('data.placement_source', 'manual')
            ->assertJsonPath('data.is_locked', true);
    }

    public function test_f11_home_match_gets_the_full_field_and_cannot_use_half(): void
    {
        $primer = $this->team('primer-equip');

        $this->create([
            'team_id' => $primer->id, 'status' => 'confirmed', 'home_away' => 'home',
            'match_date' => '2026-10-17', 'kickoff_time' => '17:00', 'field_area_id' => $this->area('camp_2')->id,
        ])->assertUnprocessable()->assertJsonPath('errors.field_area_id.0', 'Un partit de futbol 11 necessita el camp complet.');

        $this->create([
            'team_id' => $primer->id, 'status' => 'confirmed', 'home_away' => 'home',
            'match_date' => '2026-10-17', 'kickoff_time' => '17:00', 'field_area_id' => $this->area('full')->id,
        ])->assertCreated()->assertJsonPath('data.field_area.code', 'full');
    }

    public function test_home_kickoff_must_be_on_a_quarter_hour(): void
    {
        $this->create([
            'team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home',
            'match_date' => '2026-10-17', 'kickoff_time' => '10:10', 'field_area_id' => $this->area('camp_1')->id,
        ])->assertUnprocessable()->assertJsonPath('errors.kickoff_time.0', 'L\'hora ha de ser en punt, i quart, i mitja o menys quart.');
    }

    public function test_home_kickoff_must_be_between_9_and_21(): void
    {
        foreach (['08:45', '21:15'] as $time) {
            $this->create([
                'team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home',
                'match_date' => '2026-10-17', 'kickoff_time' => $time, 'field_area_id' => $this->area('camp_1')->id,
            ])->assertUnprocessable()->assertJsonPath('errors.kickoff_time.0', 'L\'hora del partit ha d\'estar entre les 09:00 i les 21:00.');
        }

        // 21:00 és l'últim inici permès i 15:00 (fora de franja) és vàlid manualment.
        foreach (['21:00', '15:00'] as $time) {
            $this->create([
                'team_id' => $this->team('benjami-a')->id, 'status' => 'confirmed', 'home_away' => 'home',
                'match_date' => '2026-10-17', 'kickoff_time' => $time, 'field_area_id' => $this->area('camp_1')->id,
            ])->assertCreated();
        }
    }

    public function test_home_placement_needs_date_time_and_field(): void
    {
        $this->create([
            'team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home',
            'match_date' => '2026-10-17', 'kickoff_time' => '11:15',
        ])->assertUnprocessable()->assertJsonPath('errors.field_area_id.0', 'Per col·locar un partit a casa cal indicar la data, l\'hora i el camp.');
    }

    public function test_away_match_stores_venue_data_and_never_occupies_the_field(): void
    {
        $this->create([
            'team_id' => $this->team('cadet-a')->id, 'status' => 'confirmed', 'home_away' => 'away', 'opponent' => 'UE Figueres',
            'match_date' => '2026-10-18', 'kickoff_time' => '10:10', 'call_time' => '08:45',
            'venue_name' => 'Camp Municipal Vilatenim', 'address' => 'Carrer del Futbol, 1', 'municipality' => 'Figueres',
            'maps_url' => 'https://maps.google.com/?q=Vilatenim',
        ])->assertCreated()
            ->assertJsonPath('data.kickoff_time', '10:10')
            ->assertJsonPath('data.field_area_id', null)
            ->assertJsonPath('data.municipality', 'Figueres');

        $this->create([
            'team_id' => $this->team('juvenil-a')->id, 'status' => 'confirmed', 'home_away' => 'away',
            'match_date' => '2026-10-18', 'kickoff_time' => '12:00', 'field_area_id' => $this->area('full')->id,
        ])->assertUnprocessable()->assertJsonPath('errors.field_area_id.0', 'Només els partits a casa ocupen un espai del camp.');
    }

    public function test_call_time_cannot_be_after_kickoff(): void
    {
        $this->create([
            'team_id' => $this->team('cadet-a')->id, 'status' => 'confirmed', 'home_away' => 'away',
            'match_date' => '2026-10-18', 'kickoff_time' => '10:00', 'call_time' => '10:30',
        ])->assertUnprocessable()->assertJsonPath('errors.call_time.0', 'La convocatòria no pot ser posterior a l\'hora del partit.');
    }

    public function test_date_must_be_inside_the_weekend(): void
    {
        $this->create([
            'team_id' => $this->team('cadet-a')->id, 'status' => 'confirmed', 'home_away' => 'away', 'match_date' => '2026-10-20',
        ])->assertUnprocessable()->assertJsonPath('errors.match_date.0', 'La data ha de ser dins de la jornada.');
    }

    public function test_home_or_away_is_required(): void
    {
        $this->create(['team_id' => $this->team('juvenil-a')->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.home_away.0', 'Indica si el partit és a casa o a fora.');
    }

    public function test_update_uses_optimistic_locking(): void
    {
        $id = $this->create(['team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home'])->json('data.id');
        $base = ['team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home'];

        $this->spa()->putJson("/api/matches/{$id}", [...$base, 'opponent' => 'Primer canvi', 'lock_version' => 0])
            ->assertOk()->assertJsonPath('data.lock_version', 1);

        // Un segon gestor que encara té la versió 0 no pot sobreescriure el canvi.
        $this->spa()->putJson("/api/matches/{$id}", [...$base, 'opponent' => 'Canvi antic', 'lock_version' => 0])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Algú altre ha modificat aquestes dades. Recarrega-les i torna-ho a provar.');

        $this->assertSame('Primer canvi', GameMatch::query()->find($id)->opponent);
    }

    public function test_switching_a_home_match_to_away_frees_the_field(): void
    {
        $team = $this->team('alevi-a');
        $id = $this->create([
            'team_id' => $team->id, 'status' => 'confirmed', 'home_away' => 'home',
            'match_date' => '2026-10-17', 'kickoff_time' => '11:15', 'field_area_id' => $this->area('camp_1')->id,
        ])->json('data.id');

        $this->spa()->putJson("/api/matches/{$id}", [
            'team_id' => $team->id, 'status' => 'confirmed', 'home_away' => 'away', 'lock_version' => 0,
            'match_date' => '2026-10-17', 'kickoff_time' => '11:00', 'venue_name' => 'Camp de Banyoles',
        ])->assertOk()
            ->assertJsonPath('data.field_area_id', null)
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.venue_name', 'Camp de Banyoles');
    }

    public function test_matches_are_listed_by_team_order_and_can_be_deleted(): void
    {
        $this->create(['team_id' => $this->team('primer-equip')->id, 'status' => 'confirmed', 'home_away' => 'home']);
        $id = $this->create(['team_id' => $this->team('escoleta-a')->id, 'status' => 'confirmed', 'home_away' => 'away'])->json('data.id');

        $this->spa()->getJson("/api/matchdays/{$this->weekend()->id}/matches")
            ->assertOk()
            ->assertJsonPath('data.0.team.name', 'Escoleta A')
            ->assertJsonPath('data.1.team.name', 'Primer equip');

        $this->spa()->deleteJson("/api/matches/{$id}")->assertNoContent();
        $this->assertSoftDeleted('matches', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'match.deleted', 'auditable_id' => $id]);
    }

    public function test_changes_are_audited(): void
    {
        $id = $this->create(['team_id' => $this->team('alevi-a')->id, 'status' => 'confirmed', 'home_away' => 'home'])->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['event' => 'match.created', 'auditable_id' => $id, 'user_id' => $this->user->id]);
    }

    public function test_call_time_defaults_to_one_hour_before_kickoff_for_away_matches_and_follows_changes(): void
    {
        $team = $this->team('cadet-a');
        $base = ['team_id' => $team->id, 'home_away' => 'away', 'match_date' => '2026-10-18'];

        $id = $this->create([...$base, 'kickoff_time' => '12:00'])->assertCreated()->assertJsonPath('data.call_time', '11:00')->json('data.id');

        // Mentre la convocatòria sigui la per defecte, segueix l'hora del partit.
        $this->spa()->putJson("/api/matches/{$id}", [...$base, 'kickoff_time' => '12:30', 'call_time' => '11:00', 'lock_version' => 0])
            ->assertOk()->assertJsonPath('data.call_time', '11:30');

        // Una convocatòria posada a mà es respecta.
        $this->spa()->putJson("/api/matches/{$id}", [...$base, 'kickoff_time' => '13:00', 'call_time' => '11:45', 'lock_version' => 1])
            ->assertOk()->assertJsonPath('data.call_time', '11:45');
    }
}
