<?php

namespace App\Domain\Imaging;

/**
 * Imatge resum dels resultats d'una jornada: títol, dates i partits acabats.
 */
final readonly class ResultsPoster
{
    /**
     * @param  list<ResultEntry>  $entries
     */
    public function __construct(
        public string $title,
        public string $dateLabel,
        public string $subtitle,
        public array $entries,
    ) {}
}
