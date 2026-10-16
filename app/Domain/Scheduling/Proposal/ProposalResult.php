<?php

namespace App\Domain\Scheduling\Proposal;

use App\Domain\Scheduling\Conflicts\Conflict;
use App\Domain\Scheduling\Model\Placement;

/**
 * Resultat d'una proposta: col·locacions, partits sense programar (amb el motiu) i conflictes finals.
 */
final readonly class ProposalResult
{
    public const UNPLACED_NO_SLOT = 'no_slot';

    public const UNPLACED_FIELD_FULL = 'field_full';

    /**
     * @param  array<int, Placement>  $placements  Partits que la proposta ha col·locat (id => col·locació).
     * @param  array<int, string>  $unplaced  Partits que no hi caben (id => motiu).
     * @param  list<int>  $relocated  Partits moguts per fer lloc a un altre.
     * @param  list<Conflict>  $conflicts  Conflictes de la programació resultant.
     */
    public function __construct(
        public array $placements,
        public array $unplaced,
        public array $relocated,
        public array $conflicts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $placements = $this->placements;
        ksort($placements);

        return [
            'placements' => array_map(fn (Placement $p) => $p->toArray(), $placements),
            'unplaced' => $this->unplaced,
            'relocated' => $this->relocated,
            'conflicts' => array_map(fn (Conflict $c) => $c->toArray(), $this->conflicts),
        ];
    }
}
