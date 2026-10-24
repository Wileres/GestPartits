<?php

namespace App\Services;

use App\Enums\GameFormat;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Team;
use App\Services\Scheduling\PreferredScheduleService;
use App\Services\Scheduling\ScheduleService;
use App\Support\CallTime;
use App\Support\ClubSettings;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Alta i modificació de partits amb valors per defecte de l'equip i normalització segons
 * la condició (casa / fora). Un partit nou es desa com a «Confirmat»; si és a casa i no s'indica horari,
 * es carrega l'horari preferent de l'equip i la convocatòria és una hora abans del partit (a casa i a fora).
 * Les modificacions usen bloqueig optimista.
 */
class MatchService
{
    /** Camps que defineixen la col·locació d'un partit a casa. */
    private const PLACEMENT_FIELDS = ['match_date', 'kickoff_time', 'field_area_id'];

    public function __construct(
        private readonly ClubSettings $settings,
        private readonly ScheduleService $schedules,
        private readonly PreferredScheduleService $preferred,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Matchday $matchday, array $data): GameMatch
    {
        return DB::transaction(function () use ($matchday, $data): GameMatch {
            $team = Team::query()->with('category')->findOrFail($data['team_id']);

            $match = new GameMatch([
                'matchday_id' => $matchday->id,
                'status' => MatchStatus::default(),
                'game_format' => $team->effectiveGameFormat(),
                'duration_min' => $team->effectiveDurationMin(),
                'post_min' => $team->effectivePostMin(),
                'show_in_image' => true,
                'is_locked' => false,
            ]);

            $this->apply($match, $team, $data);
            $this->applyPreferredSchedule($match, $team, $matchday, $data);
            $match->call_time = CallTime::follow($match->call_time, null, $match->kickoff_time);
            $match->save();
            $this->schedules->markChanged($matchday);

            return $match->load(['team.category', 'fieldArea']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ConflictHttpException si algú altre ha modificat el partit mentrestant.
     */
    public function update(GameMatch $match, array $data): GameMatch
    {
        return DB::transaction(function () use ($match, $data): GameMatch {
            $match = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->lock_version !== (int) $data['lock_version']) {
                throw new ConflictHttpException(__('messages.http.409'));
            }

            $team = Team::query()->with('category')->findOrFail($data['team_id']);
            unset($data['lock_version']);

            $oldKickoff = $match->kickoff_time;
            $this->apply($match, $team, $data);
            // La convocatòria per defecte (una hora abans) es proposa quan el partit rep hora i segueix els canvis d'hora;
            // si el gestor l'ha buidada o n'ha posat una de pròpia, es respecta.
            if ($oldKickoff === null || $match->call_time !== null) {
                $match->call_time = CallTime::follow($match->call_time, $oldKickoff, $match->kickoff_time);
            }
            $match->lock_version++;
            $match->save();
            $this->schedules->markChanged($match->loadMissing('matchday')->matchday);

            return $match->load(['team.category', 'fieldArea']);
        });
    }

    public function delete(GameMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match->delete();
            $this->schedules->markChanged($match->loadMissing('matchday')->matchday);
        });
    }

    /**
     * Crea els partits del full ràpid en una sola transacció.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, GameMatch>
     */
    public function createMany(Matchday $matchday, array $rows): array
    {
        return DB::transaction(fn (): array => array_map(fn (array $row) => $this->create($matchday, $row), $rows));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function apply(GameMatch $match, Team $team, array $data): void
    {
        $placementBefore = $match->only(self::PLACEMENT_FIELDS);

        $match->fill(array_intersect_key($data, array_flip($match->getFillable())));
        $match->team_id = $team->id;

        if (array_key_exists('game_format', $data) && $data['game_format'] === null) {
            $match->game_format = $team->effectiveGameFormat();
        }
        if (array_key_exists('duration_min', $data) && $data['duration_min'] === null) {
            $match->duration_min = $team->effectiveDurationMin();
        }
        if (array_key_exists('post_min', $data) && $data['post_min'] === null) {
            $match->post_min = $team->effectivePostMin();
        }

        if ($match->home_away === HomeAway::Home) {
            $match->venue_id = $this->settings->homeVenue()->id;
            $match->venue_name = null;
            $match->address = null;
            $match->municipality = null;
            $match->maps_url = null;

            if ($match->game_format === GameFormat::Football11 && $match->field_area_id === null && $match->kickoff_time !== null) {
                $match->field_area_id = $this->settings->homeVenue()->fieldAreas()->where('kind', 'full')->value('id');
            }

            // Una col·locació feta a mà queda bloquejada perquè la propera proposta no la mogui.
            if ($placementBefore !== $match->only(self::PLACEMENT_FIELDS) && $match->kickoff_time !== null) {
                $match->placement_source = 'manual';
                if (! array_key_exists('is_locked', $data)) {
                    $match->is_locked = true;
                }
            }

            if ($match->kickoff_time === null) {
                $match->placement_source = null;
                $match->field_area_id = null;
            }

            return;
        }

        // Partit a fora: no ocupa el camp local.
        $match->field_area_id = null;
        $match->venue_id = null;
        $match->is_locked = false;
        $match->placement_source = null;
    }

    /**
     * Partit a casa nou sense data ni hora: carrega l'horari preferent de l'equip (si en té).
     * No es bloqueja: la proposta automàtica el pot tornar a col·locar si hi ha un conflicte.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyPreferredSchedule(GameMatch $match, Team $team, Matchday $matchday, array $data): void
    {
        $given = ($data['match_date'] ?? null) !== null || ($data['kickoff_time'] ?? null) !== null || ($data['field_area_id'] ?? null) !== null;

        if ($match->home_away !== HomeAway::Home || $match->status === MatchStatus::Cancelled || $given || $match->kickoff_time !== null) {
            return;
        }

        $default = $this->preferred->placementFor($match, $team, $matchday);
        if ($default === null) {
            return;
        }

        $match->match_date = $default['date'];
        $match->kickoff_time = $default['kickoff'];
        $match->field_area_id = $default['field_area_id'];
        $match->placement_source = 'auto';
    }
}
