<?php

namespace App\Infrastructure\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\PosterFormat;
use App\Domain\Imaging\PosterPage;
use App\Domain\Imaging\ResultsPage;
use App\Domain\Imaging\ScheduleImageRenderer;

/**
 * Renderitzador de proves: PNG de la mida correcta i d'un sol color. Desa les pàgines rebudes.
 */
class FakeRenderer implements ScheduleImageRenderer
{
    /** @var list<PosterPage> */
    public array $rendered = [];

    /** @var list<ResultsPage> */
    public array $renderedResults = [];

    public function name(): string
    {
        return 'fake';
    }

    public function render(PosterPage $page, Branding $branding): string
    {
        $this->rendered[] = $page;

        return $this->png($page->poster->format);
    }

    public function renderResults(ResultsPage $page, Branding $branding): string
    {
        $this->renderedResults[] = $page;

        return $this->png($page->poster->format);
    }

    private function png(PosterFormat $format): string
    {
        $image = imagecreatetruecolor($format->width(), $format->height());
        imagefill($image, 0, 0, imagecolorallocate($image, 11, 78, 162));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
