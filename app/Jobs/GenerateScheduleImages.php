<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Services\ImageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera en segon pla les imatges d'una jornada confirmada.
 */
class GenerateScheduleImages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * @param  array{group_home_away?: bool, format?: string, compress?: bool, joined?: bool}  $options
     */
    public function __construct(public readonly int $scheduleId, public readonly ?int $userId = null, public readonly array $options = []) {}

    public function uniqueId(): string
    {
        return (string) $this->scheduleId;
    }

    public function handle(ImageService $images): void
    {
        $schedule = Schedule::query()->with('matchday')->findOrFail($this->scheduleId);

        try {
            $images->generate($schedule, $this->userId, $this->options);
        } finally {
            Cache::forget(ImageService::pendingKey($schedule));
        }
    }

    public function failed(Throwable $e): void
    {
        Cache::forget("imatges:programacio:{$this->scheduleId}");
        Log::error('No s\'han pogut generar les imatges', ['schedule_id' => $this->scheduleId, 'error' => $e->getMessage()]);
    }
}
