<?php

namespace App\Domain\Imaging;

/**
 * Pàgina que es renderitza (una imatge del format del pòster: història o publicació).
 */
final readonly class PosterPage
{
    public const MODE_NORMAL = 'normal';

    public const MODE_COMPACT = 'compact';

    /** Les files s'estiren per omplir la imatge (mode comprimit). */
    public const MODE_FILL = 'fill';

    /**
     * @param  list<PosterEntry>  $entries
     * @param  int  $rowHeight  Alçada de cada caixa de partit, en píxels.
     */
    public function __construct(
        public DayPoster $poster,
        public array $entries,
        public string $mode,
        public int $page,
        public int $totalPages,
        public int $rowHeight,
    ) {}
}
