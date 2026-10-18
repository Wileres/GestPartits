<?php

namespace App\Domain\Scheduling\Proposal;

use App\Domain\Scheduling\Conflicts\ConflictDetector;
use App\Domain\Scheduling\Model\FieldResources;
use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\RuleSpec;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Domain\Scheduling\Rules\RuleResolver;

/**
 * Proposta automàtica d'horaris per als partits a casa.
 *
 *  1. Es mouen només els partits actius a casa que no estan bloquejats.
 *  2. Els partits bloquejats i els bloquejos del camp l'ocupen abans de començar.
 *  3. Ordre: regles obligatòries, regles recomanades (per prioritat), partits sense regla; dins de cada grup,
 *     primer el que té menys possibilitats, després l'ordre de categoria i d'equip i l'id.
 *  4. Cada partit va al candidat amb la puntuació més baixa: amb preferència, el més proper al dia, hora i
 *     camp preferents; sense preferència, la primera franja amb ordre d'ompliment (dissabte matí → diumenge
 *     matí) i l'hora més aviat possible. Les finestres obligatòries (p. ex. Escoleta ≥ 10:00) filtren.
 *  5. Si un partit no hi cap, s'intenta moure un sol partit col·locat abans (reparació limitada).
 *  6. Els que no hi caben queden «sense programar» amb el motiu.
 *
 * El resultat és determinista: mateixes dades, mateixa proposta.
 */
final class ProposalEngine
{
    private const PENALTY_OTHER_DAY = 10000;

    private const PENALTY_WINDOW = 5000;

    private const PENALTY_AREA = 5;

    private const FILL_ORDER_WEIGHT = 100000;

    public function __construct(private readonly ConflictDetector $detector = new ConflictDetector) {}

    public function propose(ScheduleInput $input): ProposalResult
    {
        $resolver = new RuleResolver($input->rules, $input->referenceDate());
        $matches = $input->matches;
        usort($matches, fn (MatchItem $a, MatchItem $b) => $a->id <=> $b->id);

        $movable = array_values(array_filter($matches, fn (MatchItem $m) => $this->isMovable($m)));
        $occupancy = new Occupancy($input->blocks);

        foreach ($matches as $m) {
            if ($m->isActive() && $m->isHome() && $m->isPlaced() && ! $this->isMovable($m)) {
                $occupancy->add($m, (string) $m->date, (int) $m->kickoff, (string) $m->area);
            }
        }

        $queue = $this->order($movable, $input, $resolver, $occupancy);

        /** @var array<int, Candidate> $placed */
        $placed = [];
        $unplaced = [];
        $relocated = [];

        foreach ($queue as $match) {
            $best = $this->best($match, $input, $resolver, $occupancy);

            if ($best === null) {
                $best = $this->repair($match, $input, $resolver, $occupancy, $placed, $relocated, $queue);
            }

            if ($best === null) {
                $unplaced[$match->id] = $this->candidates($match, $input, $resolver, $occupancy, true) === []
                    ? ProposalResult::UNPLACED_NO_SLOT
                    : ProposalResult::UNPLACED_FIELD_FULL;

                continue;
            }

            $occupancy->add($match, $best->date, $best->kickoff, $best->area);
            $placed[$match->id] = $best;
        }

        ksort($placed);
        ksort($unplaced);
        sort($relocated);

        $placements = array_map(fn (Candidate $c) => $c->placement(), $placed);

        $final = array_map(function (MatchItem $m) use ($placements, $unplaced) {
            if (isset($placements[$m->id])) {
                return $m->withPlacement($placements[$m->id]);
            }

            return isset($unplaced[$m->id]) ? $m->withPlacement(null) : $m;
        }, $matches);

        return new ProposalResult($placements, $unplaced, $relocated, $this->detector->detect($input->withMatches($final)));
    }

    private function isMovable(MatchItem $m): bool
    {
        return $m->isActive() && $m->isHome() && ! $m->locked;
    }

    /**
     * @param  list<MatchItem>  $movable
     * @return list<MatchItem>
     */
    private function order(array $movable, ScheduleInput $input, RuleResolver $resolver, Occupancy $occupancy): array
    {
        $keys = [];
        foreach ($movable as $m) {
            $preference = $resolver->preference($m);
            $group = $preference === null ? 2 : ($preference->mandatory ? 0 : 1);
            $keys[$m->id] = [$group, -($preference?->priority ?? 0), count($this->candidates($m, $input, $resolver, $occupancy)), $m->categoryOrder, $m->teamOrder, $m->id];
        }

        usort($movable, fn (MatchItem $a, MatchItem $b) => $keys[$a->id] <=> $keys[$b->id]);

        return $movable;
    }

    private function best(MatchItem $match, ScheduleInput $input, RuleResolver $resolver, Occupancy $occupancy): ?Candidate
    {
        $candidates = $this->candidates($match, $input, $resolver, $occupancy);
        if ($candidates === []) {
            return null;
        }

        $preference = $resolver->preference($match);
        $windows = array_values(array_filter($resolver->windows($match), fn (RuleSpec $r) => ! $r->mandatory));

        $scored = array_map(fn (Candidate $c) => $c->withScore($this->score($c, $preference, $windows)), $candidates);
        usort($scored, Candidate::compare(...));

        return $scored[0];
    }

    /**
     * @param  list<RuleSpec>  $recommendedWindows
     */
    private function score(Candidate $c, ?RuleSpec $preference, array $recommendedWindows): int
    {
        $score = 0;

        foreach ($recommendedWindows as $window) {
            $distance = $window->windowDistance($c->kickoff);
            $score += $distance > 0 ? self::PENALTY_WINDOW + $distance : 0;
        }

        if ($preference === null) {
            return $score + (int) $c->fillOrder * self::FILL_ORDER_WEIGHT + $c->kickoff;
        }

        if ($preference->weekday !== null && ScheduleInput::isoWeekday($c->date) !== $preference->weekday) {
            $score += self::PENALTY_OTHER_DAY;
        }

        $score += abs($c->kickoff - (int) $preference->preferred);

        if ($preference->area !== null && $preference->area !== $c->area) {
            $score += self::PENALTY_AREA;
        }

        return $score;
    }

    /**
     * Totes les col·locacions vàlides: dins d'una franja (amb ordre d'ompliment si el partit no té
     * preferència), a la graella, dins l'horari operatiu, complint les finestres obligatòries i amb el camp lliure.
     *
     * @return list<Candidate>
     */
    private function candidates(MatchItem $match, ScheduleInput $input, RuleResolver $resolver, Occupancy $occupancy, bool $ignoreMatches = false): array
    {
        $hasPreference = $resolver->preference($match) !== null;
        $mandatoryWindows = array_values(array_filter($resolver->windows($match), fn (RuleSpec $r) => $r->mandatory));
        $areas = FieldResources::proposalAreas($match->format);
        $candidates = [];

        foreach ($input->dates as $date) {
            foreach ($input->slotsOn($date) as $slot) {
                if (! $hasPreference && $slot->fillOrder === null) {
                    continue;
                }

                $first = max($slot->opens, $input->firstKickoff);
                $first += ($input->step - $first % $input->step) % $input->step;
                $last = min($slot->lastKickoff, $input->lastKickoff);

                for ($kickoff = $first; $kickoff <= $last; $kickoff += $input->step) {
                    foreach ($mandatoryWindows as $window) {
                        if (! $window->windowAllows($kickoff)) {
                            continue 2;
                        }
                    }

                    foreach ($areas as $area) {
                        $key = "{$date}|{$kickoff}|{$area}";
                        if (! isset($candidates[$key]) && $occupancy->isFree($match, $date, $kickoff, $area, $ignoreMatches)) {
                            $candidates[$key] = new Candidate($date, $kickoff, $area, $slot->fillOrder);
                        }
                    }
                }
            }
        }

        return array_values($candidates);
    }

    /**
     * Intenta fer lloc movent un sol partit ja col·locat en aquesta proposta (sense regla obligatòria).
     *
     * @param  array<int, Candidate>  $placed
     * @param  list<int>  $relocated
     * @param  list<MatchItem>  $queue
     */
    private function repair(MatchItem $match, ScheduleInput $input, RuleResolver $resolver, Occupancy $occupancy, array &$placed, array &$relocated, array $queue): ?Candidate
    {
        $byId = [];
        foreach ($queue as $m) {
            $byId[$m->id] = $m;
        }

        foreach (array_reverse(array_keys($placed)) as $otherId) {
            $other = $byId[$otherId];
            if ($resolver->preference($other)?->mandatory === true) {
                continue;
            }

            $previous = $placed[$otherId];
            $occupancy->remove($otherId);

            $forMatch = $this->best($match, $input, $resolver, $occupancy);
            if ($forMatch !== null) {
                $occupancy->add($match, $forMatch->date, $forMatch->kickoff, $forMatch->area);
                $forOther = $this->best($other, $input, $resolver, $occupancy);

                if ($forOther !== null) {
                    $occupancy->add($other, $forOther->date, $forOther->kickoff, $forOther->area);
                    $placed[$otherId] = $forOther;
                    $relocated[] = $otherId;
                    $occupancy->remove($match->id);

                    return $forMatch;
                }

                $occupancy->remove($match->id);
            }

            $occupancy->add($other, $previous->date, $previous->kickoff, $previous->area);
        }

        return null;
    }
}
