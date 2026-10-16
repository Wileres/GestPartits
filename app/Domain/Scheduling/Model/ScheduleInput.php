<?php

namespace App\Domain\Scheduling\Model;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Dades completes d'una jornada per al motor: dies, graella, horari operatiu, franges, bloquejos,
 * regles i partits. Es pot crear des de la base de dades o des d'un fitxer JSON (fixtures compartides
 * amb el validador TypeScript del calendari).
 */
final readonly class ScheduleInput
{
    /**
     * @param  list<string>  $dates
     * @param  list<SlotWindow>  $slots
     * @param  list<BlockInterval>  $blocks
     * @param  list<RuleSpec>  $rules
     * @param  list<MatchItem>  $matches
     */
    public function __construct(
        public array $dates,
        public int $step,
        public int $firstKickoff,
        public int $lastKickoff,
        public array $slots,
        public array $blocks,
        public array $rules,
        public array $matches,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dates: array_values($data['dates']),
            step: (int) ($data['step_min'] ?? 15),
            firstKickoff: Minutes::parse($data['operating']['first'] ?? '09:00'),
            lastKickoff: Minutes::parse($data['operating']['last'] ?? '21:00'),
            slots: array_map(SlotWindow::fromArray(...), $data['slots'] ?? []),
            blocks: array_map(BlockInterval::fromArray(...), $data['blocks'] ?? []),
            rules: array_map(RuleSpec::fromArray(...), $data['rules'] ?? []),
            matches: array_map(MatchItem::fromArray(...), $data['matches'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dates' => $this->dates,
            'step_min' => $this->step,
            'operating' => ['first' => Minutes::format($this->firstKickoff), 'last' => Minutes::format($this->lastKickoff)],
            'slots' => array_map(fn (SlotWindow $s) => $s->toArray(), $this->slots),
            'blocks' => array_map(fn (BlockInterval $b) => $b->toArray(), $this->blocks),
            'rules' => array_map(fn (RuleSpec $r) => $r->toArray(), $this->rules),
            'matches' => array_map(fn (MatchItem $m) => $m->toArray(), $this->matches),
        ];
    }

    /**
     * @param  list<MatchItem>  $matches
     */
    public function withMatches(array $matches): self
    {
        return new self($this->dates, $this->step, $this->firstKickoff, $this->lastKickoff, $this->slots, $this->blocks, $this->rules, $matches);
    }

    /**
     * @return list<SlotWindow>
     */
    public function slotsOn(string $date): array
    {
        return array_values(array_filter($this->slots, fn (SlotWindow $s) => $s->date === $date));
    }

    /** Data de referència per a la vigència de les regles: el primer dia de la jornada. */
    public function referenceDate(): string
    {
        return $this->dates[0] ?? '1970-01-01';
    }

    public static function isoWeekday(string $date): int
    {
        return (int) (new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('N');
    }

    public function match(int $id): ?MatchItem
    {
        foreach ($this->matches as $match) {
            if ($match->id === $id) {
                return $match;
            }
        }

        return null;
    }
}
