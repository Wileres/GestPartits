<?php

namespace App\Domain\Imaging;

/**
 * Imatge d'un dia: títol («Dissabte»), data («17/10/2026») i partits en ordre cronològic.
 */
final readonly class DayPoster
{
    /**
     * @param  list<PosterEntry>  $entries
     */
    public function __construct(
        public string $date,
        public string $title,
        public string $dateLabel,
        public string $subtitle,
        public array $entries,
        /** Si és cert, els partits van agrupats («A casa» i «A fora») amb un títol petit per a cada grup. */
        public bool $grouped = false,
        /** Si és cert, tots els partits del dia van en una sola imatge i les files s'estiren per omplir-la. */
        public bool $compress = false,
        public PosterFormat $format = PosterFormat::Story,
    ) {}
}
