<?php

namespace App\Domain\Imaging;

/**
 * Una línia de la imatge: un partit (a casa o a fora).
 */
final readonly class PosterEntry
{
    public function __construct(
        public string $kickoff,
        public ?string $callTime,
        public string $team,
        public string $opponent,
        public bool $home,
        public ?string $place,
        public ?string $field = null,
    ) {}
}
