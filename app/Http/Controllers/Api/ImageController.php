<?php

namespace App\Http\Controllers\Api;

use App\Domain\Imaging\PosterFormat;
use App\Http\Controllers\Controller;
use App\Http\Resources\GeneratedImageResource;
use App\Jobs\GenerateResultsImages;
use App\Jobs\GenerateScheduleImages;
use App\Models\GeneratedImage;
use App\Models\Matchday;
use App\Models\Schedule;
use App\Services\ImageService;
use App\Support\CatalanDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $options = $request->validate([
            'group_home_away' => ['sometimes', 'boolean'],
            // Història (1080 × 1920) o publicació d'Instagram (1080 × 1350).
            'format' => ['sometimes', Rule::enum(PosterFormat::class)],
            // Tots els partits d'un dia en una sola imatge, amb les caixes estirades.
            'compress' => ['sometimes', 'boolean'],
            // Tots els partits de la jornada en una sola imatge (si no hi caben, una imatge per dia).
            'joined' => ['sometimes', 'boolean'],
        ]);

        Cache::put(ImageService::pendingKey($schedule), true, now()->addMinutes(10));
        GenerateScheduleImages::dispatch($schedule->id, $request->user()->id, [
            'group_home_away' => (bool) ($options['group_home_away'] ?? false),
            'format' => $options['format'] ?? PosterFormat::Story->value,
            'compress' => (bool) ($options['compress'] ?? false),
            'joined' => (bool) ($options['joined'] ?? false),
        ]);

        return $this->state($schedule->refresh(), 202);
    }

    /**
     * Última generació de resultats d'una jornada i si se n'està generant una.
     */
    public function results(Matchday $matchday): JsonResponse
    {
        $this->authorize('viewAny', GeneratedImage::class);

        return $this->resultsState($matchday);
    }

    public function generateResults(Request $request, Matchday $matchday): JsonResponse
    {
        $this->authorize('viewAny', GeneratedImage::class);
        $options = $request->validate(['format' => ['sometimes', Rule::enum(PosterFormat::class)]]);
        $format = PosterFormat::tryFrom((string) ($options['format'] ?? '')) ?? PosterFormat::Post;
        // Falla (422) si encara no hi ha cap resultat.
        abort_if($this->images->resultsPoster($matchday, $format)->entries === [], 422, __('images.no_results'));

        Cache::put(ImageService::resultsPendingKey($matchday), true, now()->addMinutes(10));
        GenerateResultsImages::dispatch($matchday->id, $request->user()->id, ['format' => $format->value]);

        return $this->resultsState($matchday, 202);
    }

    /**
     * Històric de generacions, de la més recent a la més antiga.
     */
    public function history(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GeneratedImage::class);

        $filters = $request->validate([
            'kind' => ['sometimes', Rule::in([GeneratedImage::KIND_SCHEDULE, GeneratedImage::KIND_RESULTS])],
            'matchday_id' => ['sometimes', 'integer'],
        ]);

        $batches = $this->images->history($filters['kind'] ?? null, isset($filters['matchday_id']) ? (int) $filters['matchday_id'] : null);

        return response()->json(['data' => $batches->map(fn (Collection $images) => $this->batch($images))->values()]);
    }

    public function download(GeneratedImage $generatedImage): StreamedResponse
    {
        $this->authorize('view', $generatedImage);
        abort_unless(Storage::disk($generatedImage->disk)->exists($generatedImage->path), 404);

        return Storage::disk($generatedImage->disk)->response($generatedImage->path, $generatedImage->downloadName(), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, GeneratedImage $generatedImage): Response
    {
        $this->authorize('delete', $generatedImage);
        $this->images->delete($generatedImage, $request->user()->id);

        return response()->noContent();
    }

    /**
     * Esborra tota una generació (totes les seves imatges).
     */
    public function destroyBatch(Request $request, string $batch): Response
    {
        $this->authorize('viewAny', GeneratedImage::class);
        abort_if($this->images->deleteBatch($batch, $request->user()->id) === 0, 404);

        return response()->noContent();
    }

    private function state(Schedule $schedule, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'generating' => $this->images->isGenerating($schedule),
            'confirmed' => $schedule->confirmed_version_id !== null && $schedule->status->value === 'confirmed',
            'images' => GeneratedImageResource::collection($this->images->current($schedule))->resolve(),
        ]], $status);
    }

    private function resultsState(Matchday $matchday, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'generating' => $this->images->isGeneratingResults($matchday),
            'results_count' => count($this->images->resultsPoster($matchday)->entries),
            'images' => GeneratedImageResource::collection($this->images->currentResults($matchday))->resolve(),
        ]], $status);
    }

    /**
     * @param  Collection<int, GeneratedImage>  $images
     * @return array<string, mixed>
     */
    private function batch(Collection $images): array
    {
        /** @var GeneratedImage $first */
        $first = $images->first();

        return [
            'batch_id' => $first->batch_id,
            'kind' => $first->kind,
            'matchday' => $first->matchday === null ? null : [
                'id' => $first->matchday->id,
                'display_label' => $first->matchday->label ?? CatalanDate::matchdayLabel($first->matchday->starts_on, $first->matchday->ends_on),
            ],
            'options' => $first->options,
            'created_at' => $first->created_at?->toIso8601String(),
            'created_by' => $first->creator?->name,
            'images' => GeneratedImageResource::collection($images)->resolve(),
        ];
    }
}
