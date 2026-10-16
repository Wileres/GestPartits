<?php

namespace App\Domain\Scheduling\Model;

/**
 * Col·locació d'un partit a casa: data, hora d'inici (minuts) i espai del camp.
 */
final readonly class Placement
{
    public function __construct(
        public string $date,
        public int $kickoff,
        public string $area,
    ) {}

    /**
     * @return array{date: string, kickoff: string, area: string}
     */
    public function toArray(): array
    {
        return ['date' => $this->date, 'kickoff' => Minutes::format($this->kickoff), 'area' => $this->area];
    }
}
