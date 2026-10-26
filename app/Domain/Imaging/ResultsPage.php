<?php

namespace App\Domain\Imaging;

/**
 * Pàgina de resultats que es renderitza (una imatge del format del pòster).
 */
final readonly class ResultsPage
{
    /**
     * @param  list<ResultEntry>  $entries
     * @param  int  $rowHeight  Alçada de cada fila: s'estiren per omplir la imatge.
     */
    public function __construct(
        public ResultsPoster $poster,
        public array $entries,
        public int $rowHeight,
        public int $page,
        public int $totalPages,
    ) {}
}
