<?php

namespace App\Domain\Scheduling\Model;

/**
 * Bloqueig del camp dins d'una data (minuts [start, end)). area null = tot el camp.
 */
final readonly class BlockInterval
{
    public function __construct(
        public string $date,
        public int $start,
        public int $end,
        public ?string $area,
        public string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $end = $data['end'] === '24:00' ? 1440 : Minutes::parse($data['end']);

        return new self((string) $data['date'], Minutes::parse($data['start']), $end, $data['area'] ?? null, (string) ($data['reason'] ?? ''));
    }

    /**
     * @return list<string>
     */
    public function resources(): array
    {
        return $this->area === null || $this->area === FieldResources::FULL ? FieldResources::HALVES : [$this->area];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'start' => Minutes::format($this->start),
            'end' => $this->end >= 1440 ? '24:00' : Minutes::format($this->end),
            'area' => $this->area,
            'reason' => $this->reason,
        ];
    }
}
