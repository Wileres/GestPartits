<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GeneratedImageResource;
use App\Jobs\GenerateScheduleImages;
use App\Models\GeneratedImage;
use App\Models\Schedule;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
    public function __construct(private readonly ImageService $images) {}

    public function index(Schedule $schedule): JsonResponse
    {
        $this->authorize('view', $schedule);

        return $this->state($schedule);
    }

    /**
     * Encua la generació (en segon pla). La interfície consulta l'estat fins que acaba.
     */
    public function generate(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);
        $this->images->ensureConfirmed($schedule);

        Cache::put(ImageService::pendingKey($schedule), true, now()->addMinutes(10));
        GenerateScheduleImages::dispatch($schedule->id, $request->user()->id);

        return $this->state($schedule->refresh(), 202);
    }

    public function download(GeneratedImage $generatedImage): StreamedResponse
    {
        $this->authorize('view', $generatedImage->loadMissing('schedule')->schedule);
        abort_unless(Storage::disk($generatedImage->disk)->exists($generatedImage->path), 404);

        return Storage::disk($generatedImage->disk)->response($generatedImage->path, sprintf('jornada-%s-%d.png', $generatedImage->day->toDateString(), $generatedImage->page), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function state(Schedule $schedule, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'generating' => $this->images->isGenerating($schedule),
            'confirmed' => $schedule->confirmed_version_id !== null && $schedule->status->value === 'confirmed',
            'images' => GeneratedImageResource::collection($this->images->current($schedule))->resolve(),
        ]], $status);
    }
}
