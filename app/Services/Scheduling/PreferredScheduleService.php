<?php

namespace App\Services\Scheduling;

use App\Domain\Scheduling\Model\FieldResources;
use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\Minutes;
use App\Domain\Scheduling\Model\RuleSpec;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Domain\Scheduling\Proposal\Occupancy;
use App\Domain\Scheduling\Rules\RuleResolver;
use App\Models\FieldArea;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Team;
use App\Support\ClubSettings;

/**
 * Horari preferent d'un equip quan juga a casa: la regla de preferència més específica
 * (equip > categoria > modalitat). Serveix per mostrar-lo a la fitxa de l'equip i per carregar-lo
 * per defecte quan es crea un partit a casa.
 */
class PreferredScheduleService
{
    public function __construct(
        private readonly ScheduleInputFactory $inputs,
        private readonly ClubSettings $settings,
    ) {}

    /**
     * Regla de preferència efectiva d'un equip (vigent a la data indicada, per defecte avui).
     *
     * @param  list<RuleSpec>|null  $rules  Regles ja carregades (per no tornar-les a llegir per a cada equip).
     */
    public function ruleFor(Team $team, ?string $date = null, ?array $rules = null): ?RuleSpec
    {
        $resolver = new RuleResolver($rules ?? $this->inputs->rules(), $date ?? now(config('gestpartits.timezone'))->toDateString());

        return $resolver->preference($this->teamItem($team));
    }

    /**
     * Col·locació per defecte d'un partit a casa nou: dia de la jornada amb el dia de la setmana de la
     * regla, hora preferent i camp (el preferent o, si no n'hi ha, el primer lliure; el futbol 11, el camp complet).
     * Retorna null si l'equip no té horari preferent o la jornada no té aquest dia de la setmana.
     *
     * @return array{date: string, kickoff: string, field_area_id: int}|null
     */
    public function placementFor(GameMatch $match, Team $team, Matchday $matchday): ?array
    {
        $dates = $this->inputs->dates($matchday);
        $rule = $this->ruleFor($team, $dates[0] ?? null);

        if ($rule === null || $rule->preferred === null) {
            return null;
        }

        $date = $rule->weekday === null
            ? ($dates[0] ?? null)
            : collect($dates)->first(fn (string $d) => ScheduleInput::isoWeekday($d) === $rule->weekday);

        if ($date === null) {
            return null;
        }

        $item = $this->matchItem($match, $team);
        $area = $this->chooseArea($item, $rule, $date, $matchday);

        $areaId = FieldArea::query()->where('venue_id', $this->settings->homeVenue()->id)->where('code', $area)->value('id');

        return $areaId === null ? null : [
            'date' => $date,
            'kickoff' => Minutes::format((int) $rule->preferred),
            'field_area_id' => (int) $areaId,
        ];
    }

    private function chooseArea(MatchItem $item, RuleSpec $rule, string $date, Matchday $matchday): string
    {
        if ($item->format === MatchItem::FORMAT_F11) {
            return FieldResources::FULL;
        }

        if ($rule->area !== null) {
            return $rule->area;
        }

        // Camp 1 o Camp 2: el primer que estigui lliure a aquesta hora segons els partits ja col·locats.
        $occupancy = new Occupancy([]);
        foreach ($this->inputs->matchModels($matchday) as $other) {
            if ($other->home_away?->value === 'home' && $other->status->isActive() && $other->match_date?->toDateString() === $date && $other->kickoff_time !== null && $other->fieldArea !== null) {
                $occupancy->add($this->inputs->matchItem($other), $date, Minutes::parse($other->kickoff_time), $other->fieldArea->code);
            }
        }

        foreach (FieldResources::HALVES as $half) {
            if ($occupancy->isFree($item, $date, (int) $rule->preferred, $half)) {
                return $half;
            }
        }

        return FieldResources::CAMP_1;
    }

    private function teamItem(Team $team): MatchItem
    {
        return new MatchItem(0, $team->id, $team->name, $team->category_id, 0, 0, $team->effectiveGameFormat()->value, 'home', 'confirmed', null, null, null,
            $team->effectiveDurationMin(), $team->effectivePostMin(), 0, null, false, null, null);
    }

    private function matchItem(GameMatch $match, Team $team): MatchItem
    {
        return new MatchItem(0, $team->id, $team->name, $team->category_id, 0, 0, $match->game_format->value, 'home', 'confirmed', null, null, null,
            $match->duration_min, $match->post_min, $team->category->min_gap_min, null, false, null, null);
    }
}
