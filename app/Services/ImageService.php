<?php

namespace App\Services;

use App\Domain\Imaging\DayPoster;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterEntry;
use App\Domain\Imaging\PosterFormat;
use App\Domain\Imaging\ResultEntry;
use App\Domain\Imaging\ResultsPoster;
use App\Domain\Imaging\ScheduleImageRenderer;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Enums\ScheduleStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Models\GameMatch;
use App\Models\GeneratedImage;
use App\Models\Matchday;
use App\Models\Schedule;
use App\Support\CatalanDate;
use App\Support\ClubBranding;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imatges verticals (1080 × 1920) per a Instagram Stories: el calendari de cada dia d'una jornada confirmada
 * i el resum dels resultats de la jornada.
 *
 * Cada generació és un lot (`batch_id`) que es conserva en un històric fins que el gestor l'esborra.
 */
class ImageService
{
    /** Estats d'un avís que impedeixen esborrar una imatge adjunta (ja s'ha enviat o s'està enviant). */
    private const BLOCKING_MESSAGE_STATUSES = [
        WhatsAppMessageStatus::Queued, WhatsAppMessageStatus::Sending, WhatsAppMessageStatus::Sent,
        WhatsAppMessageStatus::ManualSent, WhatsAppMessageStatus::Unknown,
    ];

    public function __construct(
        private readonly ScheduleImageRenderer $renderer,
        private readonly LayoutPlanner $planner,
        private readonly ClubBranding $branding,
        private readonly AuditLogger $audit,
    ) {}

    public static function pendingKey(Schedule $schedule): string
    {
        return "imatges:programacio:{$schedule->id}";
    }

    public static function resultsPendingKey(Matchday $matchday): string
    {
        return "imatges:resultats:{$matchday->id}";
    }

    public function ensureConfirmed(Schedule $schedule): void
    {
        if ($schedule->status !== ScheduleStatus::Confirmed || $schedule->confirmed_version_id === null) {
            throw ValidationException::withMessages(['schedule' => __('images.not_confirmed')]);
        }
    }

    /**
     * Pòsters dels dies de la jornada amb partits (a casa i a fora), en ordre cronològic.
     * Si s'agrupen, a cada dia van primer els partits a casa i després els de fora.
     * Si es comprimeix, tots els partits d'un dia van en una sola imatge amb les caixes estirades.
     *
     * @return list<DayPoster>
     */
    public function posters(Schedule $schedule, bool $grouped = false, bool $compress = false, PosterFormat $format = PosterFormat::Story): array
    {
        $matchday = $schedule->matchday;
        $subtitle = $matchday->label ?? '';
        $matches = GameMatch::query()->with(['team', 'fieldArea'])
            ->where('matchday_id', $matchday->id)
            ->whereIn('status', [MatchStatus::Proposed->value, MatchStatus::Confirmed->value])
            ->where('show_in_image', true)
            ->whereNotNull('match_date')->whereNotNull('kickoff_time')
            ->get()
            ->sortBy(fn (GameMatch $m) => [$m->match_date->toDateString(), $grouped && $m->home_away !== HomeAway::Home ? 1 : 0, $m->kickoff_time, $m->team->sort_order, $m->id])
            ->groupBy(fn (GameMatch $m) => $m->match_date->toDateString());

        $posters = [];
        for ($day = CarbonImmutable::parse($matchday->starts_on->toDateString()); $day->lte($matchday->ends_on); $day = $day->addDay()) {
            $date = $day->toDateString();
            $entries = ($matches[$date] ?? collect())->map(fn (GameMatch $m) => new PosterEntry(
                kickoff: (string) Time::hm($m->kickoff_time),
                callTime: Time::hm($m->call_time),
                team: $m->team->name,
                opponent: $m->opponent ?? '—',
                home: $m->home_away === HomeAway::Home,
                place: $m->home_away === HomeAway::Home ? null : (implode(' · ', array_filter([$m->venue_name, $m->municipality])) ?: null),
                field: $m->home_away === HomeAway::Home ? $m->fieldArea?->name : null,
            ))->values()->all();

            if ($entries !== []) {
                $posters[] = new DayPoster($date, Str::ucfirst(CatalanDate::weekday($day)), CatalanDate::date($day), $subtitle, $entries, $grouped, $compress, $format);
            }
        }

        return $posters;
    }

    /**
     * Pòster amb els resultats dels partits acabats de la jornada (en l'ordre dels equips del club).
     */
    public function resultsPoster(Matchday $matchday): ResultsPoster
    {
        $matches = GameMatch::query()->with('team')
            ->where('matchday_id', $matchday->id)
            ->whereIn('status', [MatchStatus::Proposed->value, MatchStatus::Confirmed->value])
            ->where('show_in_image', true)
            ->whereNotNull('score_team')->whereNotNull('score_opponent')
            ->get()
            ->sortBy(fn (GameMatch $m) => [$m->team->sort_order, $m->id]);

        $entries = $matches->map(fn (GameMatch $m) => new ResultEntry(
            team: $m->team->name,
            opponent: $m->opponent ?? '—',
            home: $m->home_away === HomeAway::Home,
            scoreTeam: (int) $m->score_team,
            scoreOpponent: (int) $m->score_opponent,
        ))->values()->all();

        $first = CatalanDate::date($matchday->starts_on);
        $last = CatalanDate::date($matchday->ends_on);

        return new ResultsPoster('Resultats', $first === $last ? $first : "{$first} – {$last}", $matchday->label ?? '', $entries, PosterFormat::Post);
    }

    /**
     * Genera i desa les imatges del calendari de la versió confirmada. Cada generació és un lot nou:
     * les anteriors es conserven a l'històric.
     *
     * @param  array{group_home_away?: bool, format?: string, compress?: bool}  $options
     * @return Collection<int, GeneratedImage>
     */
    public function generate(Schedule $schedule, ?int $userId = null, array $options = []): Collection
    {
        $schedule->loadMissing('matchday');
        $this->ensureConfirmed($schedule);

        $grouped = (bool) ($options['group_home_away'] ?? false);
        $compress = (bool) ($options['compress'] ?? false);
        $format = PosterFormat::tryFrom((string) ($options['format'] ?? '')) ?? PosterFormat::Story;
        $branding = $this->branding->forImages();
        $batch = (string) Str::ulid();
        $rendered = [];

        foreach ($this->posters($schedule, $grouped, $compress, $format) as $poster) {
            $plan = $this->planner->plan($poster);

            foreach ($plan->pages as $page) {
                $rendered[] = $this->store(
                    $this->renderer->render($page, $branding),
                    "images/{$schedule->matchday_id}/{$batch}/{$poster->date}-{$page->page}.png",
                    ['day' => $poster->date, 'page' => $page->page, 'total_pages' => $page->totalPages, 'warnings' => $page->page === 1 ? $plan->warnings : []],
                );
            }
        }

        if ($rendered === []) {
            throw ValidationException::withMessages(['schedule' => __('images.no_matches')]);
        }

        $this->persist($rendered, [
            'kind' => GeneratedImage::KIND_SCHEDULE, 'matchday_id' => $schedule->matchday_id, 'batch_id' => $batch,
            'schedule_id' => $schedule->id, 'schedule_version_id' => (int) $schedule->confirmed_version_id,
            'options' => ['group_home_away' => $grouped, 'format' => $format->value, 'compress' => $compress], 'created_by' => $userId,
        ]);

        $this->audit->log('images.generated', $schedule, null, ['version_id' => $schedule->confirmed_version_id, 'batch' => $batch, 'images' => count($rendered), 'group_home_away' => $grouped, 'format' => $format->value, 'compress' => $compress], $userId);

        return $this->batchImages($batch);
    }

    /**
     * Genera la imatge amb el resum dels resultats de la jornada.
     *
     * @return Collection<int, GeneratedImage>
     */
    public function generateResults(Matchday $matchday, ?int $userId = null): Collection
    {
        $poster = $this->resultsPoster($matchday);

        if ($poster->entries === []) {
            throw ValidationException::withMessages(['matchday' => __('images.no_results')]);
        }

        $plan = $this->planner->planResults($poster);
        $branding = $this->branding->forImages();
        $batch = (string) Str::ulid();
        $rendered = [];

        foreach ($plan->pages as $page) {
            $rendered[] = $this->store(
                $this->renderer->renderResults($page, $branding),
                "images/{$matchday->id}/{$batch}/resultats-{$page->page}.png",
                ['day' => $matchday->starts_on->toDateString(), 'page' => $page->page, 'total_pages' => $page->totalPages, 'warnings' => $page->page === 1 ? $plan->warnings : []],
            );
        }

        $this->persist($rendered, [
            'kind' => GeneratedImage::KIND_RESULTS, 'matchday_id' => $matchday->id, 'batch_id' => $batch,
            'schedule_id' => null, 'schedule_version_id' => null, 'options' => null, 'created_by' => $userId,
        ]);

        $this->audit->log('images.results_generated', $matchday, null, ['batch' => $batch, 'images' => count($rendered), 'results' => count($poster->entries)], $userId);

        return $this->batchImages($batch);
    }

    /**
     * Imatges de l'última generació del calendari d'aquesta programació (si és de la versió confirmada).
     *
     * @return Collection<int, GeneratedImage>
     */
    public function current(Schedule $schedule): Collection
    {
        if ($schedule->confirmed_version_id === null) {
            return collect();
        }

        $batch = GeneratedImage::query()
            ->where('kind', GeneratedImage::KIND_SCHEDULE)
            ->where('schedule_id', $schedule->id)
            ->where('schedule_version_id', $schedule->confirmed_version_id)
            ->orderByDesc('id')->value('batch_id');

        return $batch === null ? collect() : $this->batchImages($batch);
    }

    /**
     * Imatges de l'última generació de resultats d'una jornada.
     *
     * @return Collection<int, GeneratedImage>
     */
    public function currentResults(Matchday $matchday): Collection
    {
        $batch = GeneratedImage::query()
            ->where('kind', GeneratedImage::KIND_RESULTS)->where('matchday_id', $matchday->id)
            ->orderByDesc('id')->value('batch_id');

        return $batch === null ? collect() : $this->batchImages($batch);
    }

    /**
     * Històric: les generacions més recents (més noves primer), cadascuna amb les seves imatges.
     *
     * @return Collection<int, Collection<int, GeneratedImage>>
     */
    public function history(?string $kind = null, ?int $matchdayId = null, int $limit = 60): Collection
    {
        $batches = GeneratedImage::query()
            ->when($kind !== null, fn ($q) => $q->where('kind', $kind))
            ->when($matchdayId !== null, fn ($q) => $q->where('matchday_id', $matchdayId))
            ->whereNotNull('batch_id')
            ->select('batch_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('batch_id')->orderByDesc('last_id')->limit($limit)
            ->pluck('batch_id');

        return GeneratedImage::query()->with(['matchday', 'creator'])
            ->whereIn('batch_id', $batches)->orderByDesc('id')->orderBy('day')->orderBy('page')->get()
            ->groupBy('batch_id')
            ->map(fn (Collection $images) => $images->sortBy(fn (GeneratedImage $i) => [$i->day->toDateString(), $i->page])->values())
            ->values();
    }

    /**
     * @return Collection<int, GeneratedImage>
     */
    public function batchImages(string $batch): Collection
    {
        return GeneratedImage::query()->with(['matchday', 'creator'])->where('batch_id', $batch)->orderBy('day')->orderBy('page')->get();
    }

    public function isGenerating(Schedule $schedule): bool
    {
        return Cache::has(self::pendingKey($schedule));
    }

    public function isGeneratingResults(Matchday $matchday): bool
    {
        return Cache::has(self::resultsPendingKey($matchday));
    }

    /**
     * Esborra una imatge (i el fitxer). No es pot esborrar si està adjunta a un avís que ja s'ha enviat.
     */
    public function delete(GeneratedImage $image, ?int $userId = null): void
    {
        $this->deleteMany(collect([$image]), $userId);
    }

    /**
     * Esborra totes les imatges d'una generació.
     */
    public function deleteBatch(string $batch, ?int $userId = null): int
    {
        $images = GeneratedImage::query()->where('batch_id', $batch)->get();

        if ($images->isEmpty()) {
            return 0;
        }

        $this->deleteMany($images, $userId);

        return $images->count();
    }

    /**
     * @param  Collection<int, GeneratedImage>  $images
     */
    private function deleteMany(Collection $images, ?int $userId): void
    {
        DB::transaction(function () use ($images, $userId): void {
            foreach ($images as $image) {
                $blocked = $image->attachments()->whereHas('message', fn ($q) => $q->whereIn('status', array_map(fn (WhatsAppMessageStatus $s) => $s->value, self::BLOCKING_MESSAGE_STATUSES)))->exists();

                if ($blocked) {
                    throw ValidationException::withMessages(['image' => __('images.attached')]);
                }

                // Els esborranys d'avisos deixen de portar-la adjunta.
                $image->attachments()->delete();
                $image->delete();
                $this->audit->log('images.deleted', null, ['id' => $image->id, 'batch' => $image->batch_id, 'kind' => $image->kind, 'day' => $image->day->toDateString()], null, $userId);
            }
        });

        foreach ($images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function store(string $png, string $path, array $extra): array
    {
        Storage::disk('local')->put($path, $png);
        $size = getimagesizefromstring($png) ?: [0, 0];

        return [...$extra, 'width' => $size[0], 'height' => $size[1], 'path' => $path, 'checksum' => hash('sha256', $png)];
    }

    /**
     * @param  list<array<string, mixed>>  $rendered
     * @param  array<string, mixed>  $common
     */
    private function persist(array $rendered, array $common): void
    {
        DB::transaction(function () use ($rendered, $common): void {
            foreach ($rendered as $row) {
                GeneratedImage::query()->create([...$row, ...$common, 'disk' => 'local', 'renderer' => $this->renderer->name()]);
            }
        });
    }
}
