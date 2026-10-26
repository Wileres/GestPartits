<?php

namespace App\Domain\Imaging;

/**
 * Pàgines de resultats i avisos per al gestor.
 */
final readonly class ResultsLayoutPlan
{
    /**
     * @param  list<ResultsPage>  $pages
     * @param  list<array{code: string, params: array<string, int|string>}>  $warnings
     */
    public function __construct(public array $pages, public array $warnings) {}
}
