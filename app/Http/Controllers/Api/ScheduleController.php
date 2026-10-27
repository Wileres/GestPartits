<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedules\MoveRequest;
use App\Http\Requests\Schedules\ScheduleVersionRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Schedule;
use App\Models\ScheduleVersion;
use App\Services\Scheduling\ScheduleService;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function show(Matchday $matchday): ScheduleResource
    {
        $schedule = $this->schedules->forMatchday($matchday);
        $this->authorize('view', $schedule);

        return new ScheduleResource($this->schedules->state($schedule));
    }

    public function propose(ScheduleVersionRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->propose($schedule, $request->version()));
    }

    public function save(ScheduleVersionRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->save($schedule, $request->version()));
    }

    public function undo(ScheduleVersionRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->undo($schedule, $request->version()));
    }

    public function restore(ScheduleVersionRequest $request, Schedule $schedule, ScheduleVersion $scheduleVersion): ScheduleResource
    {
        return new ScheduleResource($this->schedules->restoreVersion($schedule, $scheduleVersion, $request->version()));
    }

    public function confirm(ScheduleVersionRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->confirm($schedule, $request->version()));
    }

    public function validateMove(MoveRequest $request, Schedule $schedule): JsonResponse
    {
        $conflicts = $this->schedules->validateMove($schedule, GameMatch::query()->findOrFail($request->integer('match_id')), $request->target());

        return response()->json([
            'data' => [
                'valid' => collect($conflicts)->every(fn (array $c) => $c['severity'] !== 'critical'),
                'conflicts' => $conflicts,
            ],
        ]);
    }

    public function move(MoveRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->move($schedule, GameMatch::query()->findOrFail($request->integer('match_id')), $request->target(), $request->integer('version')));
    }

    public function lock(ScheduleVersionRequest $request, Schedule $schedule, GameMatch $match): ScheduleResource
    {
        return new ScheduleResource($this->schedules->setLocked($schedule, $match, true, $request->version()));
    }

    public function unlock(ScheduleVersionRequest $request, Schedule $schedule, GameMatch $match): ScheduleResource
    {
        return new ScheduleResource($this->schedules->setLocked($schedule, $match, false, $request->version()));
    }

    public function compare(Schedule $schedule, ScheduleVersion $from, ScheduleVersion $to): JsonResponse
    {
        $this->authorize('view', $schedule);
        abort_unless($from->schedule_id === $schedule->id && $to->schedule_id === $schedule->id, 404);

        return response()->json(['data' => $this->schedules->compare($from, $to)]);
    }
}
