<?php

namespace App\Domain\Scheduling\Model;

/**
 * Partit tal com el veu el motor: dades de l'equip, durades i col·locació actual (si en té).
 */
final readonly class MatchItem
{
    public const FORMAT_F7 = 'f7';

    public const FORMAT_F11 = 'f11';

    public const ACTIVE_STATUSES = ['proposed', 'confirmed'];

    public function __construct(
        public int $id,
        public int $teamId,
        public string $teamName,
        public ?int $categoryId,
        public int $categoryOrder,
        public int $teamOrder,
        public string $format,
        public ?string $homeAway,
        public string $status,
        public ?string $date,
        public ?int $kickoff,
        public ?string $area,
        public int $duration,
        public int $post,
        public int $gap,
        public ?int $call,
        public bool $locked,
        public ?string $override,
        public ?string $venue,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            teamId: (int) $data['team_id'],
            teamName: (string) ($data['team'] ?? ''),
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            categoryOrder: (int) ($data['category_order'] ?? 0),
            teamOrder: (int) ($data['team_order'] ?? 0),
            format: (string) $data['format'],
            homeAway: $data['home_away'] ?? null,
            status: (string) ($data['status'] ?? 'confirmed'),
            date: $data['date'] ?? null,
            kickoff: Minutes::parseOrNull($data['kickoff'] ?? null),
            area: $data['area'] ?? null,
            duration: (int) $data['duration'],
            post: (int) ($data['post'] ?? 0),
            gap: (int) ($data['gap'] ?? 0),
            call: Minutes::parseOrNull($data['call'] ?? null),
            locked: (bool) ($data['locked'] ?? false),
            override: isset($data['override']) && $data['override'] !== '' ? (string) $data['override'] : null,
            venue: $data['venue'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'team' => $this->teamName,
            'category_id' => $this->categoryId,
            'category_order' => $this->categoryOrder,
            'team_order' => $this->teamOrder,
            'format' => $this->format,
            'home_away' => $this->homeAway,
            'status' => $this->status,
            'date' => $this->date,
            'kickoff' => $this->kickoff === null ? null : Minutes::format($this->kickoff),
            'area' => $this->area,
            'duration' => $this->duration,
            'post' => $this->post,
            'gap' => $this->gap,
            'call' => $this->call === null ? null : Minutes::format($this->call),
            'locked' => $this->locked,
            'override' => $this->override,
            'venue' => $this->venue,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isHome(): bool
    {
        return $this->homeAway === 'home';
    }

    public function isPlaced(): bool
    {
        return $this->date !== null && $this->kickoff !== null && $this->area !== null;
    }

    /** Fi de l'ocupació del camp (partit + marge posterior). */
    public function occupationEnd(int $kickoff): int
    {
        return $kickoff + $this->duration + $this->post;
    }

    public function withPlacement(?Placement $placement): self
    {
        return new self(
            $this->id, $this->teamId, $this->teamName, $this->categoryId, $this->categoryOrder, $this->teamOrder,
            $this->format, $this->homeAway, $this->status,
            $placement?->date, $placement?->kickoff, $placement?->area,
            $this->duration, $this->post, $this->gap, $this->call, $this->locked, $this->override, $this->venue,
        );
    }
}
