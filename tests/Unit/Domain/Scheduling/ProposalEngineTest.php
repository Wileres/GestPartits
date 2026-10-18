<?php

namespace Tests\Unit\Domain\Scheduling;

use App\Domain\Scheduling\Conflicts\Conflict;
use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Domain\Scheduling\Proposal\ProposalEngine;
use App\Domain\Scheduling\Proposal\ProposalResult;
use PHPUnit\Framework\TestCase;

/**
 * Motor de proposta amb les regles inicials del club (casos 8, 9, 10, 11 i 20 de l'enunciat).
 */
class ProposalEngineTest extends TestCase
{
    private const SAT = '2026-10-17';

    private const SUN = '2026-10-18';

    /** Equips: [id equip, nom, id categoria, ordre categoria, modalitat, durada]. */
    private const TEAMS = [
        'escoleta-a' => [1, 'Escoleta A', 10, 10, 'f7', 45],
        'escoleta-b' => [2, 'Escoleta B', 10, 10, 'f7', 45],
        'benjami-a' => [3, 'Benjamí A', 12, 30, 'f7', 55],
        'benjami-b' => [4, 'Benjamí B', 12, 30, 'f7', 55],
        'benjami-c' => [5, 'Benjamí C', 12, 30, 'f7', 55],
        'alevi-a' => [6, 'Aleví A', 13, 40, 'f7', 60],
        'infantil-a' => [7, 'Infantil A', 14, 50, 'f11', 75],
        'cadet-a' => [8, 'Cadet A', 15, 60, 'f11', 85],
        'juvenil-a' => [9, 'Juvenil A', 16, 70, 'f11', 95],
        'primer-equip' => [10, 'Primer equip', 17, 80, 'f11', 105],
    ];

    /**
     * @param  array<string, array<string, mixed>>  $matches  slug => camps que se sobreescriuen
     * @param  array<string, mixed>  $context
     */
    private function input(array $matches, array $context = []): ScheduleInput
    {
        $rows = [];
        $id = 0;
        foreach ($matches as $slug => $overrides) {
            [$teamId, $name, $categoryId, $categoryOrder, $format, $duration] = self::TEAMS[preg_replace('/#\d+$/', '', $slug)];
            $rows[] = [
                'id' => $overrides['id'] ?? ++$id, 'team_id' => $teamId, 'team' => $name, 'category_id' => $categoryId,
                'category_order' => $categoryOrder, 'team_order' => $teamId, 'format' => $format, 'home_away' => 'home',
                'status' => 'confirmed', 'date' => null, 'kickoff' => null, 'area' => null, 'duration' => $duration,
                'post' => 10, 'gap' => 0, 'call' => null, 'locked' => false, 'override' => null, 'venue' => null,
                ...$overrides,
            ];
        }

        return ScheduleInput::fromArray([
            'dates' => [self::SAT, self::SUN],
            'step_min' => 15,
            'operating' => ['first' => '09:00', 'last' => '21:00'],
            'slots' => $context['slots'] ?? [
                ['date' => self::SAT, 'opens' => '09:00', 'last_kickoff' => '13:00', 'fill_order' => 1],
                ['date' => self::SAT, 'opens' => '17:00', 'last_kickoff' => '19:00', 'fill_order' => null],
                ['date' => self::SUN, 'opens' => '09:00', 'last_kickoff' => '13:00', 'fill_order' => 2],
            ],
            'blocks' => $context['blocks'] ?? [],
            'rules' => $context['rules'] ?? [
                ['id' => 1, 'team_id' => 10, 'weekday' => 6, 'preferred' => '17:00', 'priority' => 100, 'mandatory' => false, 'label' => 'Primer equip'],
                ['id' => 2, 'team_id' => 3, 'weekday' => 7, 'preferred' => '12:30', 'area' => 'camp_1', 'priority' => 80, 'mandatory' => false, 'label' => 'Benjamí A'],
                ['id' => 3, 'team_id' => 4, 'weekday' => 7, 'preferred' => '12:30', 'area' => 'camp_2', 'priority' => 80, 'mandatory' => false, 'label' => 'Benjamí B'],
                ['id' => 4, 'team_id' => 5, 'weekday' => 6, 'preferred' => '12:30', 'priority' => 80, 'mandatory' => false, 'label' => 'Benjamí C'],
                ['id' => 5, 'category_id' => 13, 'weekday' => 6, 'preferred' => '11:15', 'priority' => 70, 'mandatory' => false, 'label' => 'Aleví'],
                ['id' => 6, 'category_id' => 10, 'not_before' => '10:00', 'priority' => 90, 'mandatory' => true, 'label' => 'Escoleta'],
            ],
            'matches' => $rows,
        ]);
    }

    private function fullWeekend(): ScheduleInput
    {
        return $this->input(array_fill_keys(array_keys(self::TEAMS), []));
    }

    private function placement(ProposalResult $result, int $id): array
    {
        $this->assertArrayHasKey($id, $result->placements, "El partit {$id} no s'ha col·locat");

        return $result->placements[$id]->toArray();
    }

    public function test_primer_equip_is_proposed_on_saturday_at_17(): void
    {
        $result = (new ProposalEngine)->propose($this->fullWeekend());

        $this->assertSame(['date' => self::SAT, 'kickoff' => '17:00', 'area' => 'full'], $this->placement($result, 10));
    }

    public function test_benjami_a_and_b_share_sunday_12_30_on_different_fields(): void
    {
        $result = (new ProposalEngine)->propose($this->fullWeekend());

        $this->assertSame(['date' => self::SUN, 'kickoff' => '12:30', 'area' => 'camp_1'], $this->placement($result, 3));
        $this->assertSame(['date' => self::SUN, 'kickoff' => '12:30', 'area' => 'camp_2'], $this->placement($result, 4));
    }

    public function test_benjami_c_is_proposed_on_saturday_at_12_30(): void
    {
        $result = (new ProposalEngine)->propose($this->fullWeekend());

        $placement = $this->placement($result, 5);
        $this->assertSame([self::SAT, '12:30'], [$placement['date'], $placement['kickoff']]);
    }

    public function test_alevi_teams_are_proposed_on_saturday_at_11_15(): void
    {
        $result = (new ProposalEngine)->propose($this->input(['alevi-a' => [], 'alevi-a#2' => ['team_id' => 60, 'team' => 'Aleví B']]));

        $this->assertSame([self::SAT, '11:15'], array_slice(array_values($this->placement($result, 1)), 0, 2));
        $this->assertSame([self::SAT, '11:15'], array_slice(array_values($this->placement($result, 2)), 0, 2));
        $this->assertNotSame($this->placement($result, 1)['area'], $this->placement($result, 2)['area']);
    }

    public function test_full_weekend_has_no_critical_conflicts(): void
    {
        $result = (new ProposalEngine)->propose($this->fullWeekend());

        $critical = array_filter($result->conflicts, fn (Conflict $c) => $c->isCritical());
        $this->assertSame([], array_map(fn (Conflict $c) => $c->toArray(), array_values($critical)));
        $this->assertSame([], $result->unplaced);
        $this->assertCount(10, $result->placements);
    }

    public function test_escoleta_never_starts_before_10(): void
    {
        $result = (new ProposalEngine)->propose($this->input(['escoleta-a' => [], 'escoleta-b' => []]));

        foreach ([1, 2] as $id) {
            $this->assertGreaterThanOrEqual('10:00', $this->placement($result, $id)['kickoff']);
        }
        $this->assertSame(self::SAT, $this->placement($result, 1)['date']);
    }

    public function test_matches_without_rules_fill_saturday_morning_then_sunday_morning_never_saturday_afternoon(): void
    {
        // Set partits de futbol 11 sense regla (1 h 45 min d'ocupació): tres hi caben dissabte, tres diumenge.
        $matches = [];
        foreach (range(1, 7) as $n) {
            $matches["juvenil-a#{$n}"] = ['team_id' => 900 + $n];
        }

        $result = (new ProposalEngine)->propose($this->input($matches, ['rules' => []]));

        $saturday = array_filter($result->placements, fn ($p) => $p->date === self::SAT);
        $sunday = array_filter($result->placements, fn ($p) => $p->date === self::SUN);

        $this->assertNotEmpty($saturday);
        $this->assertNotEmpty($sunday);
        foreach ($result->placements as $placement) {
            $this->assertLessThanOrEqual(13 * 60, $placement->kickoff, 'No es fa servir la tarda del dissabte');
        }
        // Dissabte s'omple primer: el primer partit comença a les 09:00 de dissabte.
        $this->assertSame(['date' => self::SAT, 'kickoff' => '09:00', 'area' => 'full'], $this->placement($result, 1));
        $this->assertSame([7 => ProposalResult::UNPLACED_FIELD_FULL], $result->unplaced);
    }

    public function test_regeneration_respects_locked_matches(): void
    {
        $input = $this->input([
            // Aleví bloquejat el diumenge a les 10:00 (fora de la seva preferència).
            'alevi-a' => ['date' => self::SUN, 'kickoff' => '10:00', 'area' => 'camp_1', 'locked' => true],
            'benjami-c' => [],
        ]);

        $result = (new ProposalEngine)->propose($input);

        $this->assertArrayNotHasKey(1, $result->placements, 'Un partit bloquejat no es mou');
        $this->assertSame([self::SAT, '12:30'], array_slice(array_values($this->placement($result, 2)), 0, 2));
    }

    public function test_locked_matches_are_not_moved_and_their_field_is_respected(): void
    {
        $input = $this->input([
            'cadet-a' => ['date' => self::SAT, 'kickoff' => '09:00', 'area' => 'full', 'locked' => true],
            'infantil-a' => [],
        ], ['rules' => []]);

        $result = (new ProposalEngine)->propose($input);

        $this->assertArrayNotHasKey(1, $result->placements);
        // El Cadet ocupa de 09:00 a 10:35: l'Infantil va després.
        $this->assertSame(['date' => self::SAT, 'kickoff' => '10:45', 'area' => 'full'], $this->placement($result, 2));
    }

    public function test_confirmed_matches_that_are_not_locked_are_repositioned(): void
    {
        // Els partits es creen «confirmats»: només el bloqueig impedeix que la proposta els mogui.
        $input = $this->input([
            'alevi-a' => ['status' => 'confirmed', 'date' => self::SUN, 'kickoff' => '10:00', 'area' => 'camp_2'],
            'cadet-a' => ['status' => 'proposed', 'date' => self::SUN, 'kickoff' => '09:00', 'area' => 'full'],
        ]);

        $result = (new ProposalEngine)->propose($input);

        $this->assertSame([self::SAT, '11:15'], array_slice(array_values($this->placement($result, 1)), 0, 2));
        $this->assertArrayHasKey(2, $result->placements);
    }

    public function test_cancelled_matches_are_ignored(): void
    {
        $result = (new ProposalEngine)->propose($this->input(['alevi-a' => ['status' => 'cancelled'], 'cadet-a' => []]));

        $this->assertArrayNotHasKey(1, $result->placements);
        $this->assertArrayHasKey(2, $result->placements);
        $this->assertArrayNotHasKey(1, $result->unplaced);
    }

    public function test_field_blocks_are_avoided(): void
    {
        $input = $this->input(['infantil-a' => []], [
            'rules' => [],
            'blocks' => [['date' => self::SAT, 'start' => '00:00', 'end' => '24:00', 'area' => null, 'reason' => 'Festa major']],
        ]);

        $this->assertSame(['date' => self::SUN, 'kickoff' => '09:00', 'area' => 'full'], $this->placement((new ProposalEngine)->propose($input), 1));
    }

    public function test_away_matches_do_not_take_the_home_field(): void
    {
        $input = $this->input([
            'cadet-a' => ['home_away' => 'away', 'date' => self::SAT, 'kickoff' => '09:00', 'venue' => 'Vilatenim'],
            'infantil-a' => [],
        ], ['rules' => []]);

        $result = (new ProposalEngine)->propose($input);

        $this->assertArrayNotHasKey(1, $result->placements);
        $this->assertSame(['date' => self::SAT, 'kickoff' => '09:00', 'area' => 'full'], $this->placement($result, 2));
    }

    public function test_unplaceable_match_reports_the_reason(): void
    {
        $input = $this->input(['infantil-a' => []], ['rules' => [], 'slots' => [['date' => self::SAT, 'opens' => '17:00', 'last_kickoff' => '19:00', 'fill_order' => null]]]);

        $result = (new ProposalEngine)->propose($input);

        $this->assertSame([1 => ProposalResult::UNPLACED_NO_SLOT], $result->unplaced);
        $this->assertSame('HOME_MATCH_UNSCHEDULED', $result->conflicts[0]->code->value);
    }

    public function test_unreachable_mandatory_preference_takes_the_nearest_slot_and_reports_it(): void
    {
        $input = $this->input([
            'cadet-a' => ['date' => self::SAT, 'kickoff' => '17:00', 'area' => 'full', 'locked' => true],
            'primer-equip' => [],
        ], ['rules' => [['id' => 1, 'team_id' => 10, 'weekday' => 6, 'preferred' => '17:00', 'priority' => 100, 'mandatory' => true, 'label' => 'Primer equip']]]);

        $result = (new ProposalEngine)->propose($input);

        // 17:00 és ocupat pel Cadet fins a les 18:35; l'hora vàlida més propera del dissabte és les 18:45.
        $this->assertSame(['date' => self::SAT, 'kickoff' => '18:45', 'area' => 'full'], $this->placement($result, 2));
        $codes = array_map(fn (Conflict $c) => [$c->code->value, $c->severity->value, $c->matchIds], $result->conflicts);
        $this->assertContains(['MANDATORY_RULE_BROKEN', 'critical', [2]], $codes);
    }

    public function test_limited_repair_moves_one_match_to_make_room(): void
    {
        // Només hi ha una hora per franja. El Primer equip (preferència recomanada, dissabte 09:00) es col·loca
        // primer; el Juvenil (sense regla) només pot anar dissabte (única franja amb ordre d'ompliment).
        $input = $this->input(['primer-equip' => [], 'juvenil-a' => []], [
            'slots' => [
                ['date' => self::SAT, 'opens' => '09:00', 'last_kickoff' => '09:00', 'fill_order' => 1],
                ['date' => self::SUN, 'opens' => '09:00', 'last_kickoff' => '09:00', 'fill_order' => null],
            ],
            'rules' => [['id' => 1, 'team_id' => 10, 'weekday' => 6, 'preferred' => '09:00', 'priority' => 100, 'mandatory' => false]],
        ]);

        $result = (new ProposalEngine)->propose($input);

        $this->assertSame(['date' => self::SAT, 'kickoff' => '09:00', 'area' => 'full'], $this->placement($result, 2));
        $this->assertSame(['date' => self::SUN, 'kickoff' => '09:00', 'area' => 'full'], $this->placement($result, 1));
        $this->assertSame([1], $result->relocated);
        $this->assertSame([], $result->unplaced);
    }

    public function test_result_is_deterministic_regardless_of_input_order(): void
    {
        $input = $this->fullWeekend();
        $shuffled = $input->matches;
        mt_srand(2026);
        shuffle($shuffled);

        $first = (new ProposalEngine)->propose($input)->toArray();
        $second = (new ProposalEngine)->propose($input->withMatches($shuffled))->toArray();

        $this->assertSame($first, $second);
    }

    public function test_running_the_proposal_again_gives_the_same_schedule(): void
    {
        $input = $this->fullWeekend();
        $first = (new ProposalEngine)->propose($input);

        // Es tornen a passar els partits amb la col·locació proposada (no bloquejada, estat «proposat»).
        $again = $input->withMatches(array_map(
            fn ($m) => MatchItem::fromArray([...$m->withPlacement($first->placements[$m->id] ?? null)->toArray(), 'status' => 'proposed']),
            $input->matches,
        ));

        $this->assertSame($first->toArray(), (new ProposalEngine)->propose($again)->toArray());
    }
}
