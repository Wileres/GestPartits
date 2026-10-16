<?php

namespace App\Domain\Imaging;

/**
 * Pàgina que es renderitza (una imatge 1080 × 1920).
 */
final readonly class PosterPage
{
    public const MODE_NORMAL = 'normal';

    public const MODE_COMPACT = 'compact';

    /**
     * @param  list<PosterEntry>  $entries
     */
    public function __construct(
        public DayPoster $poster,
        public array $entries,
        public string $mode,
        public int $page,
        public int $totalPages,
    ) {}
}
