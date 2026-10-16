<?php

namespace App\Domain\Imaging;

/**
 * Estratègia de renderització de les imatges (Chromium, GD o una de prova). Els serveis i els
 * controladors només depenen d'aquesta interfície.
 */
interface ScheduleImageRenderer
{
    public function name(): string;

    /**
     * @return string Imatge PNG de LayoutPlanner::WIDTH × LayoutPlanner::HEIGHT píxels.
     */
    public function render(PosterPage $page, Branding $branding): string;
}
