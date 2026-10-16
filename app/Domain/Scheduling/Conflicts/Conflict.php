<?php

namespace App\Domain\Scheduling\Conflicts;

/**
 * Conflicte detectat. El text en català el construeix la capa d'aplicació a partir del codi i dels paràmetres.
 */
final readonly class Conflict
{
    /**
     * @param  list<int>  $matchIds
     * @param  array<string, scalar|null>  $params
     */
    public function __construct(
        public ConflictCode $code,
        public Severity $severity,
        public array $matchIds,
        public array $params = [],
    ) {}

    public function isCritical(): bool
    {
        return $this->severity === Severity::Critical;
    }

    public function involves(int $matchId): bool
    {
        return in_array($matchId, $this->matchIds, true);
    }

    /**
     * @return array{code: string, severity: string, match_ids: list<int>, params: array<string, scalar|null>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'severity' => $this->severity->value,
            'match_ids' => $this->matchIds,
            'params' => $this->params,
        ];
    }
}
