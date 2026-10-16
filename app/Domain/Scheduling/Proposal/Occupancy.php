<?php

namespace App\Domain\Scheduling\Proposal;

use App\Domain\Scheduling\Model\BlockInterval;
use App\Domain\Scheduling\Model\FieldResources;
use App\Domain\Scheduling\Model\MatchItem;

/**
 * Ocupació del camp durant la construcció d'una proposta (per data i recurs).
 */
final class Occupancy
{
    /** @var array<int|string, array{date: string, start: int, end: int, gap: int, resources: list<string>, block: bool}> */
    private array $entries = [];

    /**
     * @param  list<BlockInterval>  $blocks
     */
    public function __construct(array $blocks)
    {
        foreach ($blocks as $index => $block) {
            $this->entries['block-'.$index] = [
                'date' => $block->date, 'start' => $block->start, 'end' => $block->end,
                'gap' => 0, 'resources' => $block->resources(), 'block' => true,
            ];
        }
    }

    public function add(MatchItem $match, string $date, int $kickoff, string $area): void
    {
        $this->entries[$match->id] = [
            'date' => $date,
            'start' => $kickoff,
            'end' => $match->occupationEnd($kickoff),
            'gap' => $match->gap,
            'resources' => FieldResources::for($match->format, $area),
            'block' => false,
        ];
    }

    public function remove(int $matchId): void
    {
        unset($this->entries[$matchId]);
    }

    public function has(int $matchId): bool
    {
        return isset($this->entries[$matchId]);
    }

    /**
     * Cert si el partit hi cap sense tocar cap ocupació ni bloqueig. Amb $ignoreMatches només es tenen
     * en compte els bloquejos (per saber si el problema és la falta de franges o que el camp és ple).
     */
    public function isFree(MatchItem $match, string $date, int $kickoff, string $area, bool $ignoreMatches = false): bool
    {
        $resources = FieldResources::for($match->format, $area);
        $end = $match->occupationEnd($kickoff);

        foreach ($this->entries as $key => $entry) {
            if ($entry['date'] !== $date || $key === $match->id || ($ignoreMatches && ! $entry['block'])) {
                continue;
            }

            if (array_intersect($resources, $entry['resources']) === []) {
                continue;
            }

            $gap = $entry['block'] ? 0 : max($match->gap, $entry['gap']);

            if ($kickoff < $entry['end'] + $gap && $entry['start'] < $end + $gap) {
                return false;
            }
        }

        return true;
    }
}
