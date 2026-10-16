<?php

namespace App\Domain\Imaging;

/**
 * Resultat del planificador: pàgines i avisos per al gestor.
 */
final readonly class LayoutPlan
{
    public const WARNING_COMPACT = 'compact';

    public const WARNING_PAGINATED = 'paginated';

    public const WARNING_LONG_NAMES = 'long_names';

    /**
     * @param  list<PosterPage>  $pages
     * @param  list<array{code: string, params: array<string, int|string>}>  $warnings
     */
    public function __construct(public array $pages, public array $warnings) {}
}
