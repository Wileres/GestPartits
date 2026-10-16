<?php

namespace Tests\Feature\Communications;

use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Schedule;
use App\Services\Scheduling\ScheduleService;

/**
 * Prepara una jornada confirmada amb partits a casa i a fora el dissabte i el diumenge.
 */
trait ConfirmsSchedule
{
    /**
     * @return array<string, GameMatch>
     */
    protected function matchesFor(Matchday $matchday): array
    {
        $create = fn (string $slug, array $data) => GameMatch::query()->create([
            'matchday_id' => $matchday->id, 'team_id' => $this->team($slug)->id, 'status' => MatchStatus::Pending,
            'game_format' => $this->team($slug)->effectiveGameFormat(), 'duration_min' => $this->team($slug)->effectiveDurationMin(), 'post_min' => 10,
            ...$data,
        ]);

        return [
            'alevi' => $create('alevi-a', ['opponent' => 'CF Olot', 'home_away' => HomeAway::Home, 'match_date' => '2026-10-17', 'kickoff_time' => '11:15', 'call_time' => '10:30', 'field_area_id' => $this->area('camp_1')->id]),
            'primer' => $create('primer-equip', ['opponent' => 'UE Llagostera', 'home_away' => HomeAway::Home, 'match_date' => '2026-10-17', 'kickoff_time' => '17:00', 'field_area_id' => $this->area('full')->id]),
            'cadet' => $create('cadet-a', ['opponent' => 'UE Figueres', 'home_away' => HomeAway::Away, 'match_date' => '2026-10-18', 'kickoff_time' => '10:00', 'call_time' => '08:45', 'venue_name' => 'Camp Municipal Vilatenim', 'address' => 'Carrer del Futbol, 1', 'municipality' => 'Figueres']),
        ];
    }

    protected function confirmed(Matchday $matchday): Schedule
    {
        $service = app(ScheduleService::class);
        $schedule = $service->forMatchday($matchday);
        $service->confirm($schedule, $schedule->refresh()->lock_version);

        return $schedule->refresh();
    }
}
