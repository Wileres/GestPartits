<?php

namespace App\Domain\Imaging;

/**
 * Una línia de la imatge de resultats: un partit acabat.
 */
final readonly class ResultEntry
{
    public function __construct(
        public string $team,
        public string $opponent,
        public bool $home,
        public int $scoreTeam,
        public int $scoreOpponent,
    ) {}
}
