<?php

namespace App\Domain\Scheduling\Model;

/**
 * Regla de programació: àmbit (equip, categoria o modalitat), preferència i/o finestra.
 */
final readonly class RuleSpec
{
    public function __construct(
        public int $id,
        public ?int $teamId,
        public ?int $categoryId,
        public ?string $format,
        public ?int $weekday,
        public ?int $preferred,
        public ?int $notBefore,
        public ?int $notAfter,
        public ?string $area,
        public int $priority,
        public bool $mandatory,
        public string $label = '',
        public ?string $validFrom = null,
        public ?string $validTo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            format: $data['format'] ?? null,
            weekday: isset($data['weekday']) ? (int) $data['weekday'] : null,
            preferred: Minutes::parseOrNull($data['preferred'] ?? null),
            notBefore: Minutes::parseOrNull($data['not_before'] ?? null),
            notAfter: Minutes::parseOrNull($data['not_after'] ?? null),
            area: $data['area'] ?? null,
            priority: (int) ($data['priority'] ?? 0),
            mandatory: (bool) ($data['mandatory'] ?? false),
            label: (string) ($data['label'] ?? ''),
            validFrom: $data['valid_from'] ?? null,
            validTo: $data['valid_to'] ?? null,
        );
    }

    /**
     * Especificitat de l'àmbit: equip (3) > categoria (2) > modalitat (1).
     */
    public function specificity(): int
    {
        return match (true) {
            $this->teamId !== null => 3,
            $this->categoryId !== null => 2,
            default => 1,
        };
    }

    public function appliesTo(MatchItem $match): bool
    {
        return match (true) {
            $this->teamId !== null => $this->teamId === $match->teamId,
            $this->categoryId !== null => $this->categoryId === $match->categoryId,
            $this->format !== null => $this->format === $match->format,
            default => false,
        };
    }

    public function isValidOn(string $date): bool
    {
        return ($this->validFrom === null || $date >= $this->validFrom) && ($this->validTo === null || $date <= $this->validTo);
    }

    public function hasPreference(): bool
    {
        return $this->preferred !== null;
    }

    public function hasWindow(): bool
    {
        return $this->notBefore !== null || $this->notAfter !== null;
    }

    public function windowAllows(int $kickoff): bool
    {
        return ($this->notBefore === null || $kickoff >= $this->notBefore) && ($this->notAfter === null || $kickoff <= $this->notAfter);
    }

    /** Distància (minuts) fins a la finestra; 0 si la compleix. */
    public function windowDistance(int $kickoff): int
    {
        if ($this->notBefore !== null && $kickoff < $this->notBefore) {
            return $this->notBefore - $kickoff;
        }

        if ($this->notAfter !== null && $kickoff > $this->notAfter) {
            return $kickoff - $this->notAfter;
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'category_id' => $this->categoryId,
            'format' => $this->format,
            'weekday' => $this->weekday,
            'preferred' => $this->preferred === null ? null : Minutes::format($this->preferred),
            'not_before' => $this->notBefore === null ? null : Minutes::format($this->notBefore),
            'not_after' => $this->notAfter === null ? null : Minutes::format($this->notAfter),
            'area' => $this->area,
            'priority' => $this->priority,
            'mandatory' => $this->mandatory,
            'label' => $this->label,
            'valid_from' => $this->validFrom,
            'valid_to' => $this->validTo,
        ];
    }
}
