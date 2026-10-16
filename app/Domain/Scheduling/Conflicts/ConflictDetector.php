<?php

namespace App\Domain\Scheduling\Conflicts;

use App\Domain\Scheduling\Model\FieldResources;
use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\Minutes;
use App\Domain\Scheduling\Model\RuleSpec;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Domain\Scheduling\Rules\RuleResolver;

/**
 * Detecta tots els conflictes d'una programació. No amaga res: el resultat és determinista i ordenat
 * (primer els crítics). La mateixa lògica està replicada a resources/frontend/src/domain/scheduling i
 * totes dues implementacions passen les fixtures de tests/fixtures/scheduling.
 */
final class ConflictDetector
{
    /**
     * @param  bool  $forConfirmation  Si és cert, un partit a casa sense horari és crític.
     * @return list<Conflict>
     */
    public function detect(ScheduleInput $input, bool $forConfirmation = false): array
    {
        $resolver = new RuleResolver($input->rules, $input->referenceDate());
        $active = array_values(array_filter($input->matches, fn (MatchItem $m) => $m->isActive()));
        usort($active, fn (MatchItem $a, MatchItem $b) => $a->id <=> $b->id);

        $placedHome = array_values(array_filter($active, fn (MatchItem $m) => $m->isHome() && $m->isPlaced()));

        $conflicts = [
            ...$this->overlaps($placedHome),
            ...$this->placementChecks($input, $placedHome),
            ...$this->ruleChecks($resolver, $placedHome),
            ...$this->matchChecks($active, $forConfirmation),
        ];

        usort($conflicts, fn (Conflict $a, Conflict $b) => [$a->severity === Severity::Critical ? 0 : 1, $a->code->value, $a->matchIds]
            <=> [$b->severity === Severity::Critical ? 0 : 1, $b->code->value, $b->matchIds]);

        return $conflicts;
    }

    /**
     * Conflictes d'un partit concret (per a la validació d'un moviment).
     *
     * @return list<Conflict>
     */
    public function detectFor(ScheduleInput $input, int $matchId): array
    {
        return array_values(array_filter($this->detect($input), fn (Conflict $c) => $c->involves($matchId)));
    }

    /**
     * @param  list<MatchItem>  $placed
     * @return list<Conflict>
     */
    private function overlaps(array $placed): array
    {
        $conflicts = [];
        $capacitySets = [];

        // Tres o més partits de futbol 7 alhora.
        $f7 = array_values(array_filter($placed, fn (MatchItem $m) => $m->format === MatchItem::FORMAT_F7));
        foreach ($f7 as $m) {
            $simultaneous = array_values(array_filter($f7, fn (MatchItem $o) => $o->date === $m->date
                && $o->kickoff <= $m->kickoff && $m->kickoff < $o->occupationEnd($o->kickoff)));

            if (count($simultaneous) >= 3) {
                $ids = array_map(fn (MatchItem $o) => $o->id, $simultaneous);
                sort($ids);
                $capacitySets[implode(',', $ids)] = $ids;
            }
        }

        foreach ($capacitySets as $ids) {
            $conflicts[] = new Conflict(ConflictCode::F7CapacityExceeded, Severity::Critical, $ids);
        }

        // Solapaments entre parelles que comparteixen algun recurs.
        $count = count($placed);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $placed[$i];
                $b = $placed[$j];

                if ($a->date !== $b->date || ! $this->overlap($a, $b) || array_intersect(FieldResources::for($a->format, $a->area), FieldResources::for($b->format, $b->area)) === []) {
                    continue;
                }

                $ids = [$a->id, $b->id];
                $f11Count = (int) ($a->format === MatchItem::FORMAT_F11) + (int) ($b->format === MatchItem::FORMAT_F11);

                if ($f11Count === 2) {
                    $conflicts[] = new Conflict(ConflictCode::F11OverlapsF11, Severity::Critical, $ids);
                } elseif ($f11Count === 1) {
                    $conflicts[] = new Conflict(ConflictCode::F11OverlapsF7, Severity::Critical, $ids);
                } elseif (! $this->inSameCapacitySet($capacitySets, $a->id, $b->id)) {
                    $conflicts[] = new Conflict(ConflictCode::AreaOverlap, Severity::Critical, $ids);
                }
            }
        }

        return $conflicts;
    }

    private function overlap(MatchItem $a, MatchItem $b): bool
    {
        $gap = max($a->gap, $b->gap);

        return $a->kickoff < $b->occupationEnd($b->kickoff) + $gap && $b->kickoff < $a->occupationEnd($a->kickoff) + $gap;
    }

    /**
     * @param  array<string, list<int>>  $sets
     */
    private function inSameCapacitySet(array $sets, int $a, int $b): bool
    {
        foreach ($sets as $ids) {
            if (in_array($a, $ids, true) && in_array($b, $ids, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<MatchItem>  $placed
     * @return list<Conflict>
     */
    private function placementChecks(ScheduleInput $input, array $placed): array
    {
        $conflicts = [];

        foreach ($placed as $m) {
            $ids = [$m->id];

            if (! in_array($m->date, $input->dates, true)) {
                $conflicts[] = new Conflict(ConflictCode::OutsideMatchday, Severity::Critical, $ids);

                continue;
            }

            if ($m->format === MatchItem::FORMAT_F11 && $m->area !== FieldResources::FULL) {
                $conflicts[] = new Conflict(ConflictCode::F11RequiresFullField, Severity::Critical, $ids);
            }

            if ($m->format === MatchItem::FORMAT_F7 && $m->area === FieldResources::FULL) {
                $conflicts[] = new Conflict(ConflictCode::F7OnFullField, Severity::Warning, $ids);
            }

            if ($m->kickoff < $input->firstKickoff || $m->kickoff > $input->lastKickoff) {
                $conflicts[] = new Conflict(ConflictCode::OutsideOperatingHours, Severity::Critical, $ids, [
                    'from' => Minutes::format($input->firstKickoff),
                    'to' => Minutes::format($input->lastKickoff),
                ]);
            } elseif ($m->kickoff % $input->step !== 0) {
                $conflicts[] = new Conflict(ConflictCode::OffGrid, Severity::Critical, $ids, ['step' => $input->step]);
            } else {
                $inSlot = false;
                foreach ($input->slotsOn($m->date) as $slot) {
                    $inSlot = $inSlot || $slot->contains($m->kickoff);
                }
                if (! $inSlot) {
                    $conflicts[] = new Conflict(ConflictCode::OutsideSlot, Severity::Warning, $ids);
                }
            }

            $resources = FieldResources::for($m->format, $m->area);
            $end = $m->occupationEnd($m->kickoff);
            foreach ($input->blocks as $block) {
                if ($block->date === $m->date && $m->kickoff < $block->end && $block->start < $end && array_intersect($resources, $block->resources()) !== []) {
                    $conflicts[] = new Conflict(ConflictCode::FieldBlocked, Severity::Critical, $ids, ['reason' => $block->reason]);
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  list<MatchItem>  $placed
     * @return list<Conflict>
     */
    private function ruleChecks(RuleResolver $resolver, array $placed): array
    {
        $conflicts = [];

        foreach ($placed as $m) {
            $preference = $resolver->preference($m);
            if ($preference !== null && ! $this->preferenceMet($preference, $m)) {
                $conflicts[] = $this->ruleConflict($preference, $m);
            }

            foreach ($resolver->windows($m) as $window) {
                if (! $window->windowAllows((int) $m->kickoff)) {
                    $conflicts[] = $this->ruleConflict($window, $m);
                }
            }
        }

        return $conflicts;
    }

    public function preferenceMet(RuleSpec $rule, MatchItem $m): bool
    {
        return ($rule->weekday === null || ScheduleInput::isoWeekday((string) $m->date) === $rule->weekday)
            && $m->kickoff === $rule->preferred
            && ($rule->area === null || $rule->area === $m->area);
    }

    private function ruleConflict(RuleSpec $rule, MatchItem $m): Conflict
    {
        $params = [
            'rule_id' => $rule->id,
            'rule' => $rule->label,
            'weekday' => $rule->weekday,
            'preferred' => $rule->preferred === null ? null : Minutes::format($rule->preferred),
            'not_before' => $rule->notBefore === null ? null : Minutes::format($rule->notBefore),
            'not_after' => $rule->notAfter === null ? null : Minutes::format($rule->notAfter),
        ];

        if (! $rule->mandatory) {
            return new Conflict(ConflictCode::RecommendedRuleNotMet, Severity::Warning, [$m->id], $params);
        }

        // Una excepció justificada converteix l'incompliment en un avís.
        return new Conflict(ConflictCode::MandatoryRuleBroken, $m->override === null ? Severity::Critical : Severity::Warning, [$m->id], [...$params, 'override' => $m->override]);
    }

    /**
     * @param  list<MatchItem>  $active
     * @return list<Conflict>
     */
    private function matchChecks(array $active, bool $forConfirmation): array
    {
        $conflicts = [];

        $byTeam = [];
        foreach ($active as $m) {
            $byTeam[$m->teamId][] = $m->id;
        }
        foreach ($byTeam as $teamId => $ids) {
            if (count($ids) > 1) {
                $conflicts[] = new Conflict(ConflictCode::DuplicateTeam, Severity::Critical, $ids, ['team_id' => $teamId]);
            }
        }

        foreach ($active as $m) {
            if ($m->kickoff !== null && $m->call !== null && $m->call > $m->kickoff) {
                $conflicts[] = new Conflict(ConflictCode::CallAfterKickoff, Severity::Critical, [$m->id]);
            }

            if ($m->isHome() && ! $m->isPlaced()) {
                $conflicts[] = new Conflict(ConflictCode::HomeMatchUnscheduled, $forConfirmation ? Severity::Critical : Severity::Warning, [$m->id]);
            }

            if ($m->homeAway === 'away' && ($m->date === null || $m->kickoff === null || $m->venue === null || $m->venue === '')) {
                $conflicts[] = new Conflict(ConflictCode::AwayMissingData, Severity::Warning, [$m->id]);
            }
        }

        return $conflicts;
    }
}
