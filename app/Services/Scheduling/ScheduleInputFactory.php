<?php

namespace App\Services\Scheduling;

use App\Domain\Scheduling\Model\BlockInterval;
use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\Minutes;
use App\Domain\Scheduling\Model\RuleSpec;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Domain\Scheduling\Model\SlotWindow;
use App\Models\AvailabilitySlot;
use App\Models\FieldBlock;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\SchedulingRule;
use App\Support\ClubSettings;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Construeix l'entrada del motor (ScheduleInput) a partir de la base de dades.
 */
class ScheduleInputFactory
{
    public function __construct(private readonly ClubSettings $settings) {}

    public function forMatchday(Matchday $matchday): ScheduleInput
    {
        $venueId = $this->settings->homeVenue()->id;
        $dates = $this->dates($matchday);
        $hours = $this->settings->operatingHours();

        return new ScheduleInput(
            dates: $dates,
            step: $this->settings->kickoffStepMin(),
            firstKickoff: Minutes::parse($hours['first_kickoff']),
            lastKickoff: Minutes::parse($hours['last_kickoff']),
            slots: $this->slots($venueId, $dates),
            blocks: $this->blocks($venueId, $dates),
            rules: $this->rules(),
            matches: $this->matches($matchday),
        );
    }

    /**
     * @return list<string>
     */
    private function dates(Matchday $matchday): array
    {
        $dates = [];
        for ($day = CarbonImmutable::parse($matchday->starts_on->toDateString()); $day->lte($matchday->ends_on); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * Franges efectives: si una data té excepcions, només valen les actives d'aquella data (cap = dia tancat).
     *
     * @param  list<string>  $dates
     * @return list<SlotWindow>
     */
    private function slots(int $venueId, array $dates): array
    {
        $all = AvailabilitySlot::query()->where('venue_id', $venueId)->orderBy('opens_at')->orderBy('id')->get();
        $windows = [];

        foreach ($dates as $date) {
            $exceptions = $all->filter(fn (AvailabilitySlot $s) => $s->specific_date?->toDateString() === $date);
            $weekday = CarbonImmutable::parse($date)->isoWeekday();
            $effective = $exceptions->isNotEmpty() ? $exceptions : $all->filter(fn (AvailabilitySlot $s) => $s->weekday === $weekday);

            foreach ($effective->where('is_active', true) as $slot) {
                $windows[] = new SlotWindow($date, Time::toMinutes($slot->opens_at), Time::toMinutes($slot->last_kickoff_at), $slot->default_fill_order, $slot->label);
            }
        }

        return $windows;
    }

    /**
     * Bloquejos retallats a cada dia de la jornada (minuts [inici, final) dins del dia).
     *
     * @param  list<string>  $dates
     * @return list<BlockInterval>
     */
    private function blocks(int $venueId, array $dates): array
    {
        $first = $dates[0].' 00:00:00';
        $last = CarbonImmutable::parse(end($dates))->addDay()->toDateString().' 00:00:00';

        $blocks = FieldBlock::query()->with('fieldArea')->where('venue_id', $venueId)
            ->where('starts_at', '<', $last)->where('ends_at', '>', $first)->orderBy('starts_at')->orderBy('id')->get();

        $intervals = [];
        foreach ($dates as $date) {
            $dayStart = CarbonImmutable::parse($date.' 00:00:00', 'UTC');
            $dayEnd = $dayStart->addDay();

            foreach ($blocks as $block) {
                $start = CarbonImmutable::parse($block->starts_at->format('Y-m-d H:i:s'), 'UTC');
                $end = CarbonImmutable::parse($block->ends_at->format('Y-m-d H:i:s'), 'UTC');

                if ($start->gte($dayEnd) || $end->lte($dayStart)) {
                    continue;
                }

                $from = $start->lt($dayStart) ? 0 : (int) $dayStart->diffInMinutes($start);
                $to = $end->gt($dayEnd) ? 1440 : (int) $dayStart->diffInMinutes($end);

                $intervals[] = new BlockInterval($date, $from, $to, $block->fieldArea?->code, $block->reason);
            }
        }

        return $intervals;
    }

    /**
     * @return list<RuleSpec>
     */
    private function rules(): array
    {
        return SchedulingRule::query()->with(['team', 'category', 'preferredFieldArea'])->where('is_active', true)->orderBy('id')->get()
            ->map(fn (SchedulingRule $rule) => new RuleSpec(
                id: $rule->id,
                teamId: $rule->team_id,
                categoryId: $rule->category_id,
                format: $rule->game_format?->value,
                weekday: $rule->weekday,
                preferred: Minutes::parseOrNull($rule->preferred_time),
                notBefore: Minutes::parseOrNull($rule->not_before),
                notAfter: Minutes::parseOrNull($rule->not_after),
                area: $rule->preferredFieldArea?->code,
                priority: $rule->priority,
                mandatory: $rule->strength->value === 'mandatory',
                label: $rule->notes ?? ($rule->team?->name ?? $rule->category?->name ?? ''),
                validFrom: $rule->valid_from?->toDateString(),
                validTo: $rule->valid_to?->toDateString(),
            ))->values()->all();
    }

    /**
     * @return list<MatchItem>
     */
    private function matches(Matchday $matchday): array
    {
        return $this->matchModels($matchday)->map(fn (GameMatch $m) => $this->matchItem($m))->values()->all();
    }

    /**
     * @return Collection<int, GameMatch>
     */
    public function matchModels(Matchday $matchday): Collection
    {
        return GameMatch::query()->with(['team.category', 'fieldArea'])->where('matchday_id', $matchday->id)->orderBy('id')->get();
    }

    public function matchItem(GameMatch $m): MatchItem
    {
        return new MatchItem(
            id: $m->id,
            teamId: $m->team_id,
            teamName: $m->team->name,
            categoryId: $m->team->category_id,
            categoryOrder: $m->team->category->sort_order,
            teamOrder: $m->team->sort_order,
            format: $m->game_format->value,
            homeAway: $m->home_away?->value,
            status: $m->status->value,
            date: $m->match_date?->toDateString(),
            kickoff: Minutes::parseOrNull($m->kickoff_time),
            area: $m->fieldArea?->code,
            duration: $m->duration_min,
            post: $m->post_min,
            gap: $m->team->category->min_gap_min,
            call: Minutes::parseOrNull($m->call_time),
            locked: $m->is_locked,
            override: $m->rule_override_reason,
            venue: $m->venue_name ?? $m->municipality,
        );
    }
}
