<?php

namespace App\Jobs;

use App\Models\Matchday;
use App\Services\ImageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera en segon pla la imatge resum dels resultats d'una jornada.
 */
class GenerateResultsImages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $matchdayId, public readonly ?int $userId = null, public readonly array $options = []) {}

    public function uniqueId(): string
    {
        return (string) $this->matchdayId;
    }

    public function handle(ImageService $images): void
    {
        $matchday = Matchday::query()->findOrFail($this->matchdayId);

        try {
            $images->generateResults($matchday, $this->userId, $this->options);
        } finally {
            Cache::forget(ImageService::resultsPendingKey($matchday));
        }
    }

    public function failed(Throwable $e): void
    {
        Cache::forget("imatges:resultats:{$this->matchdayId}");
        Log::error('No s\'ha pogut generar la imatge de resultats', ['matchday_id' => $this->matchdayId, 'error' => $e->getMessage()]);
    }
}
