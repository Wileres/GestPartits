<?php

namespace App\Domain\Imaging;

/**
 * Pàgina de resultats que es renderitza (una imatge 1080 × 1920).
 */
final readonly class ResultsPage
{
    public const MODE_NORMAL = 'normal';

    public const MODE_COMPACT = 'compact';

    public const MODE_DENSE = 'dense';

    /**
     * @param  list<ResultEntry>  $entries
     */
    public function __construct(
        public ResultsPoster $poster,
        public array $entries,
        public string $mode,
        public int $page,
        public int $totalPages,
    ) {}
}
