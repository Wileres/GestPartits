<?php

namespace App\Services\Scheduling;

use App\Domain\Scheduling\Conflicts\Conflict;
use App\Domain\Scheduling\Conflicts\ConflictDetector;
use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\Placement;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Domain\Scheduling\Proposal\ProposalEngine;
use App\Domain\Scheduling\Proposal\ProposalResult;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleVersionKind;
use App\Events\ScheduleChanged;
use App\Events\ScheduleConfirmed;
use App\Models\FieldArea;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Schedule;
use App\Models\ScheduleVersion;
use App\Services\AuditLogger;
use App\Support\CallTime;
use App\Support\ClubSettings;
use App\Support\Time;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Casos d'ús de la programació d'una jornada: proposta, moviments, bloqueig, desar, desfer,
 * comparar versions i confirmar. Totes les escriptures es fan dins d'una transacció amb bloqueig
 * de fila i comproven la versió que coneix el client (bloqueig optimista).
 */
class ScheduleService
{
    /** Camps de cada partit que es desen a les instantànies de versió. */
    private const SNAPSHOT_FIELDS = ['match_date', 'kickoff_time', 'field_area_id', 'status', 'is_locked', 'placement_source'];

    public function __construct(
        private readonly ScheduleInputFactory $inputs,
        private readonly ConflictDetector $detector,
        private readonly ProposalEngine $engine,
        private readonly ConflictPresenter $presenter,
        private readonly ClubSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function forMatchday(Matchday $matchday): Schedule
    {
        return Schedule::query()->firstOrCreate(
            ['matchday_id' => $matchday->id, 'venue_id' => $this->settings->homeVenue()->id],
            ['status' => ScheduleStatus::Draft],
        );
    }

    /**
     * Estat complet de la programació per a l'API.
     *
     * @return array<string, mixed>
     */
    public function state(Schedule $schedule): array
    {
        $schedule->refresh()->load(['matchday', 'confirmer', 'currentVersion']);
        $input = $this->inputs->forMatchday($schedule->matchday);

        return [
            'schedule' => $schedule,
            'input' => $input,
            'matches' => $this->inputs->matchModels($schedule->matchday),
            'conflicts' => $this->presenter->present($this->detector->detect($input), $input),
            'blocking_conflicts' => $this->presenter->present($this->criticalForConfirmation($input), $input),
            // Comparació sense tenir en compte l'ordre de les claus (MySQL reordena les claus dels objectes JSON).
            'has_unsaved_changes' => $schedule->currentVersion === null || $schedule->currentVersion->snapshot != $this->snapshot($schedule->matchday),
            'versions' => $schedule->versions()->with('creator')->get(),
        ];
    }

    /**
     * Genera una proposta automàtica per als partits a casa no bloquejats ni confirmats.
     *
     * @return array<string, mixed> Estat + resum de la proposta i diferències amb la situació anterior.
     */
    public function propose(Schedule $schedule, int $version): array
    {
        [$result, $before, $after] = DB::transaction(function () use ($schedule, $version) {
            $schedule = $this->lock($schedule, $version);
            $matchday = $schedule->matchday;

            $before = $this->createVersion($schedule, ScheduleVersionKind::PreProposal, __('schedule.versions.pre_proposal'));
            $result = $this->engine->propose($this->inputs->forMatchday($matchday));
            $this->applyProposal($matchday, $result);

            $after = $this->createVersion($schedule, ScheduleVersionKind::Proposal, __('schedule.versions.proposal', [
                'placed' => count($result->placements),
                'unplaced' => count($result->unplaced),
            ]), $result->conflicts);

            $schedule->status = ScheduleStatus::Proposed;
            $this->bump($schedule, $after);

            $this->audit->log('schedule.proposed', $schedule, null, [
                'placed' => count($result->placements),
                'unplaced' => array_keys($result->unplaced),
                'relocated' => $result->relocated,
            ]);

            return [$result, $before, $after];
        });

        ScheduleChanged::dispatch($schedule->matchday);

        return [
            ...$this->state($schedule),
            'proposal' => [
                'placed' => array_keys($result->placements),
                'unplaced' => array_map(fn (string $reason) => ['reason' => $reason, 'message' => __('conflicts.unplaced.'.$reason)], $result->unplaced),
                'relocated' => $result->relocated,
                'changes' => $this->compare($before, $after),
            ],
        ];
    }

    /**
     * Valida un moviment sense desar-lo: conflictes del partit a la nova posició.
     *
     * @param  array{match_date: string|null, kickoff_time: string|null, field_area_id: int|null}  $target
     * @return list<array<string, mixed>>
     */
    public function validateMove(Schedule $schedule, GameMatch $match, array $target): array
    {
        $this->ensureMovable($schedule, $match, $target);

        $input = $this->inputs->forMatchday($schedule->matchday);
        $placement = $this->placementFromTarget($target);
        $hypothetical = $input->withMatches(array_map(
            fn (MatchItem $m) => $m->id === $match->id ? $m->withPlacement($placement) : $m,
            $input->matches,
        ));

        return $this->presenter->present($this->detector->detectFor($hypothetical, $match->id), $hypothetical);
    }

    /**
     * Mou un partit (o el torna a «sense programar» si el destí és buit). Un moviment manual el bloqueja.
     *
     * @param  array{match_date: string|null, kickoff_time: string|null, field_area_id: int|null}  $target
     * @return array<string, mixed>
     */
    public function move(Schedule $schedule, GameMatch $match, array $target, int $version): array
    {
        DB::transaction(function () use ($schedule, $match, $target, $version) {
            $schedule = $this->lock($schedule, $version);
            $match = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->ensureMovable($schedule, $match, $target);

            $old = $match->only(['match_date', 'kickoff_time', 'field_area_id']);
            $unschedule = $target['kickoff_time'] === null;
            $oldKickoff = $match->kickoff_time;

            $match->match_date = $target['match_date'];
            $match->kickoff_time = $unschedule ? null : $target['kickoff_time'];
            $match->field_area_id = $unschedule ? null : $target['field_area_id'];
            $match->placement_source = $unschedule ? null : 'manual';
            $match->is_locked = ! $unschedule;
            // La convocatòria per defecte (una hora abans) segueix el partit; una de pròpia es manté.
            $match->call_time = CallTime::follow($match->call_time, $oldKickoff, $match->kickoff_time);
            $match->lock_version++;
            $match->save();

            $this->markReviewIfConfirmed($schedule);
            $this->bump($schedule);

            $this->audit->log('schedule.match_moved', $match, $old, $match->only(['match_date', 'kickoff_time', 'field_area_id']));
        });

        ScheduleChanged::dispatch($schedule->matchday);

        return $this->state($schedule);
    }

    /**
     * @return array<string, mixed>
     */
    public function setLocked(Schedule $schedule, GameMatch $match, bool $locked, int $version): array
    {
        DB::transaction(function () use ($schedule, $match, $locked, $version) {
            $schedule = $this->lock($schedule, $version);
            $match = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->ensureBelongs($schedule, $match);

            $match->is_locked = $locked;
            $match->lock_version++;
            $match->save();
            $this->bump($schedule);

            $this->audit->log($locked ? 'schedule.match_locked' : 'schedule.match_unlocked', $match);
        });

        return $this->state($schedule);
    }

    /**
     * Desa un esborrany (punt de restauració). Mai no envia cap avís.
     *
     * @return array<string, mixed>
     */
    public function save(Schedule $schedule, int $version): array
    {
        DB::transaction(function () use ($schedule, $version) {
            $schedule = $this->lock($schedule, $version);

            $saved = $this->createVersion($schedule, ScheduleVersionKind::ManualSave, __('schedule.versions.manual_save'));
            if ($schedule->status !== ScheduleStatus::Confirmed && $schedule->status !== ScheduleStatus::NeedsReview) {
                $schedule->status = ScheduleStatus::Draft;
            }
            $this->bump($schedule, $saved);

            $this->audit->log('schedule.saved', $schedule, null, ['version' => $saved->number]);
        });

        return $this->state($schedule);
    }

    /**
     * Torna a l'última versió desada (descarta els canvis posteriors) o, si no n'hi ha, a l'anterior.
     *
     * @return array<string, mixed>
     */
    public function undo(Schedule $schedule, int $version): array
    {
        DB::transaction(function () use ($schedule, $version) {
            $schedule = $this->lock($schedule, $version);
            $current = $schedule->currentVersion;
            $now = $this->snapshot($schedule->matchday);

            $target = $current !== null && $current->snapshot != $now
                ? $current
                : ($current === null ? null : $schedule->versions()->where('number', '<', $current->number)->orderByDesc('number')->first());

            if ($target === null) {
                throw ValidationException::withMessages(['schedule' => __('schedule.nothing_to_undo')]);
            }

            $this->restore($schedule->matchday, $target->snapshot);

            $undo = $this->createVersion($schedule, ScheduleVersionKind::Undo, __('schedule.versions.undo', ['number' => $target->number]));
            $this->markReviewIfConfirmed($schedule);
            $this->bump($schedule, $undo);

            $this->audit->log('schedule.undone', $schedule, null, ['restored_version' => $target->number]);
        });

        ScheduleChanged::dispatch($schedule->matchday);

        return $this->state($schedule);
    }

    /**
     * Confirma la programació si no hi ha conflictes crítics. És idempotent.
     *
     * @return array<string, mixed>
     */
    public function confirm(Schedule $schedule, int $version): array
    {
        $confirmed = DB::transaction(function () use ($schedule, $version) {
            $schedule = $this->lock($schedule, $version);

            // Doble clic o reintent: ja confirmada i sense canvis.
            if ($schedule->status === ScheduleStatus::Confirmed) {
                return null;
            }

            $input = $this->inputs->forMatchday($schedule->matchday);
            $blocking = $this->criticalForConfirmation($input);

            if ($blocking !== []) {
                throw new HttpResponseException(response()->json([
                    'message' => __('schedule.cannot_confirm'),
                    'conflicts' => $this->presenter->present($blocking, $input),
                ], 423));
            }

            // Confirmar la programació confirma els partits que encara eren una proposta.
            GameMatch::query()->where('matchday_id', $schedule->matchday_id)
                ->where('status', MatchStatus::Proposed->value)
                ->get()
                ->each(function (GameMatch $match): void {
                    $match->status = MatchStatus::Confirmed;
                    $match->lock_version++;
                    $match->save();
                });

            $version = $this->createVersion($schedule, ScheduleVersionKind::Confirmation, __('schedule.versions.confirmation'), $this->detector->detect($this->inputs->forMatchday($schedule->matchday)));

            $schedule->status = ScheduleStatus::Confirmed;
            $schedule->confirmed_at = now();
            $schedule->confirmed_by = Auth::id();
            $schedule->confirmed_version_id = $version->id;
            $this->bump($schedule, $version);

            $this->audit->log('schedule.confirmed', $schedule, null, ['version' => $version->number]);

            return [$schedule, $version];
        });

        if ($confirmed !== null) {
            ScheduleConfirmed::dispatch(...$confirmed);
        }

        return $this->state($schedule);
    }

    /**
     * Diferències entre dues versions (només els partits que canvien).
     *
     * @return list<array<string, mixed>>
     */
    public function compare(ScheduleVersion $from, ScheduleVersion $to): array
    {
        $areas = FieldArea::query()->pluck('name', 'id');
        $matches = GameMatch::withTrashed()->with('team')->whereIn('id', array_unique([...array_keys($from->snapshot), ...array_keys($to->snapshot)]))->get()->keyBy('id');
        $changes = [];

        foreach ($to->snapshot + $from->snapshot as $id => $_) {
            $a = $from->snapshot[$id] ?? null;
            $b = $to->snapshot[$id] ?? null;

            if ($a == $b) {
                continue;
            }

            $describe = fn (?array $s) => $s === null ? null : [
                'match_date' => $s['match_date'],
                'kickoff_time' => Time::hm($s['kickoff_time']),
                'field_area' => $s['field_area_id'] === null ? null : ($areas[$s['field_area_id']] ?? null),
                'status' => $s['status'],
                'is_locked' => $s['is_locked'],
            ];

            $changes[] = [
                'match_id' => (int) $id,
                'team' => $matches->get($id)?->team?->name,
                'opponent' => $matches->get($id)?->opponent,
                'from' => $describe($a),
                'to' => $describe($b),
            ];
        }

        usort($changes, fn ($x, $y) => $x['match_id'] <=> $y['match_id']);

        return $changes;
    }

    /**
     * Un partit s'ha creat, modificat o esborrat des de la pantalla de partits.
     */
    public function markChanged(Matchday $matchday): void
    {
        $schedule = Schedule::query()->where('matchday_id', $matchday->id)->lockForUpdate()->first();

        if ($schedule !== null) {
            $this->markReviewIfConfirmed($schedule);
            $schedule->lock_version++;
            $schedule->save();
        }

        ScheduleChanged::dispatch($matchday);
    }

    /**
     * @return list<Conflict>
     */
    private function criticalForConfirmation(ScheduleInput $input): array
    {
        return array_values(array_filter($this->detector->detect($input, forConfirmation: true), fn (Conflict $c) => $c->isCritical()));
    }

    private function lock(Schedule $schedule, int $version): Schedule
    {
        $locked = Schedule::query()->with(['matchday', 'currentVersion'])->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

        if ($locked->lock_version !== $version) {
            throw new ConflictHttpException(__('schedule.stale'));
        }

        return $locked;
    }

    private function bump(Schedule $schedule, ?ScheduleVersion $version = null): void
    {
        $schedule->lock_version++;
        if ($version !== null) {
            $schedule->current_version_id = $version->id;
        }
        $schedule->save();
    }

    private function markReviewIfConfirmed(Schedule $schedule): void
    {
        if ($schedule->status === ScheduleStatus::Confirmed) {
            $schedule->status = ScheduleStatus::NeedsReview;
        }
    }

    /**
     * @param  list<Conflict>  $conflicts
     */
    private function createVersion(Schedule $schedule, ScheduleVersionKind $kind, string $summary, array $conflicts = []): ScheduleVersion
    {
        return ScheduleVersion::query()->create([
            'schedule_id' => $schedule->id,
            'number' => (int) $schedule->versions()->max('number') + 1,
            'kind' => $kind,
            'snapshot' => $this->snapshot($schedule->matchday),
            'conflicts' => array_map(fn (Conflict $c) => $c->toArray(), $conflicts),
            'summary' => $summary,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function snapshot(Matchday $matchday): array
    {
        return GameMatch::query()->where('matchday_id', $matchday->id)->orderBy('id')->get()
            ->mapWithKeys(fn (GameMatch $m) => [$m->id => [
                'match_date' => $m->match_date?->toDateString(),
                'kickoff_time' => $m->kickoff_time,
                'field_area_id' => $m->field_area_id,
                'status' => $m->status->value,
                'is_locked' => $m->is_locked,
                'placement_source' => $m->placement_source,
            ]])->all();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $snapshot
     */
    private function restore(Matchday $matchday, array $snapshot): void
    {
        $matches = GameMatch::query()->where('matchday_id', $matchday->id)->whereIn('id', array_keys($snapshot))->lockForUpdate()->get();

        foreach ($matches as $match) {
            $saved = $snapshot[$match->id];
            $match->fill(array_intersect_key($saved, array_flip(self::SNAPSHOT_FIELDS)));
            $match->is_locked = (bool) $saved['is_locked'];
            $match->placement_source = $saved['placement_source'];
            if ($match->isDirty()) {
                $match->lock_version++;
                $match->save();
            }
        }
    }

    private function applyProposal(Matchday $matchday, ProposalResult $result): void
    {
        $areas = FieldArea::query()->where('venue_id', $this->settings->homeVenue()->id)->pluck('id', 'code');
        $ids = [...array_keys($result->placements), ...array_keys($result->unplaced)];

        foreach (GameMatch::query()->where('matchday_id', $matchday->id)->whereIn('id', $ids)->lockForUpdate()->get() as $match) {
            $placement = $result->placements[$match->id] ?? null;
            $oldKickoff = $match->kickoff_time;

            if ($placement instanceof Placement) {
                $match->match_date = $placement->date;
                $match->kickoff_time = Time::fromMinutes($placement->kickoff).':00';
                $match->field_area_id = $areas[$placement->area];
                $match->placement_source = 'auto';
                $match->call_time = CallTime::follow($match->call_time, $oldKickoff, $match->kickoff_time);
            } else {
                $match->kickoff_time = null;
                $match->field_area_id = null;
                $match->placement_source = null;
            }

            if ($match->isDirty()) {
                $match->lock_version++;
                $match->save();
            }
        }
    }

    /**
     * @param  array{match_date: string|null, kickoff_time: string|null, field_area_id: int|null}  $target
     */
    private function placementFromTarget(array $target): ?Placement
    {
        if ($target['kickoff_time'] === null || $target['match_date'] === null || $target['field_area_id'] === null) {
            return null;
        }

        return new Placement($target['match_date'], Time::toMinutes($target['kickoff_time']), (string) FieldArea::query()->whereKey($target['field_area_id'])->value('code'));
    }

    private function ensureBelongs(Schedule $schedule, GameMatch $match): void
    {
        if ($match->matchday_id !== $schedule->matchday_id) {
            throw ValidationException::withMessages(['match_id' => __('schedule.match_not_in_matchday')]);
        }
    }

    /**
     * @param  array{match_date: string|null, kickoff_time: string|null, field_area_id: int|null}  $target
     */
    private function ensureMovable(Schedule $schedule, GameMatch $match, array $target): void
    {
        $this->ensureBelongs($schedule, $match);

        if ($match->home_away !== HomeAway::Home || ! $match->status->isActive()) {
            throw ValidationException::withMessages(['match_id' => __('schedule.only_active_home')]);
        }

        if ($target['kickoff_time'] !== null && $match->game_format->value === 'f11'
            && FieldArea::query()->whereKey($target['field_area_id'])->value('code') !== FieldArea::CODE_FULL) {
            throw ValidationException::withMessages(['field_area_id' => __('messages.validation.f11_full_field')]);
        }
    }
}
