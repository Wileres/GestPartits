<?php

namespace App\Domain\Scheduling\Model;

/**
 * Franja efectiva d'una data: primera i última hora d'inici i ordre d'ompliment (null = no s'omple automàticament).
 */
final readonly class SlotWindow
{
    public function __construct(
        public string $date,
        public int $opens,
        public int $lastKickoff,
        public ?int $fillOrder,
        public string $label = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['date'],
            Minutes::parse($data['opens']),
            Minutes::parse($data['last_kickoff']),
            isset($data['fill_order']) ? (int) $data['fill_order'] : null,
            (string) ($data['label'] ?? ''),
        );
    }

    public function contains(int $kickoff): bool
    {
        return $kickoff >= $this->opens && $kickoff <= $this->lastKickoff;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'opens' => Minutes::format($this->opens),
            'last_kickoff' => Minutes::format($this->lastKickoff),
            'fill_order' => $this->fillOrder,
            'label' => $this->label,
        ];
    }
}
