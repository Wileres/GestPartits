<?php

namespace App\Domain\Scheduling\Proposal;

use App\Domain\Scheduling\Model\FieldResources;
use App\Domain\Scheduling\Model\Placement;

/**
 * Col·locació possible d'un partit amb la seva puntuació (com més baixa, millor).
 */
final readonly class Candidate
{
    public function __construct(
        public string $date,
        public int $kickoff,
        public string $area,
        public ?int $fillOrder,
        public int $score = 0,
    ) {}

    public function withScore(int $score): self
    {
        return new self($this->date, $this->kickoff, $this->area, $this->fillOrder, $score);
    }

    public function placement(): Placement
    {
        return new Placement($this->date, $this->kickoff, $this->area);
    }

    /**
     * Ordre total determinista: puntuació, data, hora i espai (Camp 1 abans que Camp 2).
     */
    public static function compare(self $a, self $b): int
    {
        return [$a->score, $a->date, $a->kickoff, FieldResources::ORDER[$a->area]] <=> [$b->score, $b->date, $b->kickoff, FieldResources::ORDER[$b->area]];
    }
}
