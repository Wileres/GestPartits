<?php

namespace App\Infrastructure\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterPage;
use App\Domain\Imaging\ResultsPage;
use App\Domain\Imaging\ScheduleImageRenderer;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Clip;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * Renderitza la imatge amb Chromium sense interfície: HTML i CSS propis (vista images.day-poster),
 * fonts i escut incrustats (data:), sense cap recurs extern.
 */
class ChromiumRenderer implements ScheduleImageRenderer
{
    public function __construct(private readonly ViewFactory $views, private readonly string $binary) {}

    public function name(): string
    {
        return 'chromium';
    }

    public function html(PosterPage $page, Branding $branding): string
    {
        return $this->view('images.day-poster', $page, $branding);
    }

    public function resultsHtml(ResultsPage $page, Branding $branding): string
    {
        return $this->view('images.results-poster', $page, $branding);
    }

    public function render(PosterPage $page, Branding $branding): string
    {
        return $this->screenshot($this->html($page, $branding));
    }

    public function renderResults(ResultsPage $page, Branding $branding): string
    {
        return $this->screenshot($this->resultsHtml($page, $branding));
    }

    private function view(string $name, PosterPage|ResultsPage $page, Branding $branding): string
    {
        return $this->views->make($name, [
            'page' => $page,
            'branding' => $branding,
            'logo' => $branding->logoPng === null ? null : base64_encode($branding->logoPng),
            'fonts' => [
                'latin' => base64_encode((string) file_get_contents(resource_path('fonts/inter-latin-wght-normal.woff2'))),
                'latin_ext' => base64_encode((string) file_get_contents(resource_path('fonts/inter-latin-ext-wght-normal.woff2'))),
            ],
        ])->render();
    }

    private function screenshot(string $html): string
    {
        $browser = (new BrowserFactory($this->binary))->createBrowser([
            'windowSize' => [LayoutPlanner::WIDTH, LayoutPlanner::HEIGHT],
            'headless' => true,
            'noSandbox' => true,
            'customFlags' => ['--disable-gpu', '--hide-scrollbars', '--font-render-hinting=none', '--disable-extensions', '--no-first-run'],
            'keepAlive' => false,
        ]);

        try {
            $tab = $browser->createPage();
            $tab->setViewport(LayoutPlanner::WIDTH, LayoutPlanner::HEIGHT)->await();
            $tab->setHtml($html, 15000);
            // Les fonts incrustades s'han de carregar abans de la captura.
            $tab->evaluate('document.fonts.ready.then(() => true)')->getReturnValue(10000);

            return base64_decode($tab->screenshot([
                'format' => 'png',
                'clip' => new Clip(0, 0, LayoutPlanner::WIDTH, LayoutPlanner::HEIGHT),
            ])->getBase64(30000));
        } finally {
            $browser->close();
        }
    }
}
