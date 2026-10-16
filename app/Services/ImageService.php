<?php

namespace App\Services;

use App\Domain\Imaging\DayPoster;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterEntry;
use App\Domain\Imaging\ScheduleImageRenderer;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Enums\ScheduleStatus;
use App\Models\GameMatch;
use App\Models\GeneratedImage;
use App\Models\Schedule;
use App\Support\CatalanDate;
use App\Support\ClubBranding;
use App\Support\ClubSettings;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imatges verticals (1080 × 1920) de cada dia d'una jornada confirmada, per a Instagram Stories.
 */
class ImageService
{
    public function __construct(
        private readonly ScheduleImageRenderer $renderer,
        private readonly LayoutPlanner $planner,
        private readonly ClubBranding $branding,
        private readonly ClubSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public static function pendingKey(Schedule $schedule): string
    {
        return "imatges:programacio:{$schedule->id}";
    }

    public function ensureConfirmed(Schedule $schedule): void
    {
        if ($schedule->status !== ScheduleStatus::Confirmed || $schedule->confirmed_version_id === null) {
            throw ValidationException::withMessages(['schedule' => __('images.not_confirmed')]);
        }
    }

    /**
     * Pòsters dels dies de la jornada amb partits (a casa i a fora), en ordre cronològic.
     *
     * @return list<DayPoster>
     */
    public function posters(Schedule $schedule): array
    {
        $matchday = $schedule->matchday;
        $subtitle = $matchday->label ?? '';
        $matches = GameMatch::query()->with(['team', 'fieldArea'])
            ->where('matchday_id', $matchday->id)
            ->whereIn('status', [MatchStatus::Pending->value, MatchStatus::Proposed->value, MatchStatus::Confirmed->value])
            ->where('show_in_image', true)
            ->whereNotNull('match_date')->whereNotNull('kickoff_time')
            ->get()
            ->sortBy(fn (GameMatch $m) => [$m->match_date->toDateString(), $m->kickoff_time, $m->team->sort_order, $m->id])
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
                $posters[] = new DayPoster($date, Str::ucfirst(CatalanDate::weekday($day)), CatalanDate::date($day), $subtitle, $entries);
            }
        }

        return $posters;
    }

    /**
     * Genera i desa les imatges de la versió confirmada (substitueix les anteriors d'aquesta versió).
     *
     * @return Collection<int, GeneratedImage>
     */
    public function generate(Schedule $schedule, ?int $userId = null): Collection
    {
        $schedule->loadMissing('matchday');
        $this->ensureConfirmed($schedule);

        $branding = $this->branding->forImages();
        $versionId = (int) $schedule->confirmed_version_id;
        $rendered = [];

        foreach ($this->posters($schedule) as $poster) {
            $plan = $this->planner->plan($poster);

            foreach ($plan->pages as $page) {
                $png = $this->renderer->render($page, $branding);
                $path = "images/{$schedule->id}/{$versionId}/{$poster->date}-{$page->page}.png";
                Storage::disk('local')->put($path, $png);

                $size = getimagesizefromstring($png) ?: [0, 0];
                $rendered[] = [
                    'day' => $poster->date, 'page' => $page->page, 'total_pages' => $page->totalPages,
                    'width' => $size[0], 'height' => $size[1], 'path' => $path, 'checksum' => hash('sha256', $png),
                    'warnings' => $page->page === 1 ? $plan->warnings : [],
                ];
            }
        }

        if ($rendered === []) {
            throw ValidationException::withMessages(['schedule' => __('images.no_matches')]);
        }

        DB::transaction(function () use ($schedule, $versionId, $rendered, $userId): void {
            $old = GeneratedImage::query()->where('schedule_version_id', $versionId)->get();
            $keep = array_column($rendered, 'path');
            foreach ($old as $image) {
                if (! in_array($image->path, $keep, true)) {
                    Storage::disk($image->disk)->delete($image->path);
                }
                $image->delete();
            }

            foreach ($rendered as $row) {
                GeneratedImage::query()->create([
                    ...$row,
                    'schedule_id' => $schedule->id,
                    'schedule_version_id' => $versionId,
                    'disk' => 'local',
                    'renderer' => $this->renderer->name(),
                    'created_by' => $userId,
                ]);
            }
        });

        $this->audit->log('images.generated', $schedule, null, ['version_id' => $versionId, 'images' => count($rendered)], $userId);

        return $this->current($schedule);
    }

    /**
     * @return Collection<int, GeneratedImage>
     */
    public function current(Schedule $schedule): Collection
    {
        if ($schedule->confirmed_version_id === null) {
            return collect();
        }

        return GeneratedImage::query()->where('schedule_version_id', $schedule->confirmed_version_id)->orderBy('day')->orderBy('page')->get();
    }

    public function isGenerating(Schedule $schedule): bool
    {
        return Cache::has(self::pendingKey($schedule));
    }

    /**
     * Esborra les imatges de versions antigues (tasca programada). Es conserven les de la versió confirmada actual.
     */
    public function pruneOlderThan(int $days): int
    {
        $current = Schedule::query()->whereNotNull('confirmed_version_id')->pluck('confirmed_version_id');
        $old = GeneratedImage::query()->whereNotIn('schedule_version_id', $current)->where('created_at', '<', now()->subDays($days))->get();

        foreach ($old as $image) {
            Storage::disk($image->disk)->delete($image->path);
            $image->delete();
        }

        return $old->count();
    }
}
