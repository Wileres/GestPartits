<?php

namespace App\Infrastructure\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterPage;
use App\Domain\Imaging\ResultsPage;
use App\Domain\Imaging\ScheduleImageRenderer;
use GdImage;
use RuntimeException;

/**
 * Alternativa sense Chromium: dibuixa una versió més sòbria de la imatge amb GD i fonts TTF del sistema.
 */
class GdRenderer implements ScheduleImageRenderer
{
    public function __construct(private readonly string $regularFont, private readonly string $boldFont) {}

    public function name(): string
    {
        return 'gd';
    }

    public function render(PosterPage $page, Branding $branding): string
    {
        [$image, $primary, $white, $text, $muted] = $this->canvas($page->poster->title, $page->poster->dateLabel, $page->poster->subtitle, $branding);

        $compact = $page->mode === PosterPage::MODE_COMPACT;
        $rowHeight = $compact ? LayoutPlanner::COMPACT_ROW : LayoutPlanner::NORMAL_ROW;
        $y = LayoutPlanner::HEADER;
        $previousHome = null;

        foreach ($page->entries as $index => $entry) {
            // Títol petit de cada grup («A casa» / «A fora»).
            if ($page->poster->grouped && ($index === 0 || $entry->home !== $previousHome)) {
                imagettftext($image, 24, 0, 68, $y + 36, $white, $this->boldFont, $entry->home ? 'A CASA' : 'A FORA');
                imagefilledrectangle($image, 230, $y + 25, LayoutPlanner::WIDTH - 68, $y + 27, $white);
                $y += LayoutPlanner::HEADING + LayoutPlanner::ROW_GAP;
            }
            $previousHome = $entry->home;

            imagefilledrectangle($image, 56, $y, LayoutPlanner::WIDTH - 56, $y + $rowHeight, $white);
            imagettftext($image, $compact ? 38 : 46, 0, 84, $y + ($compact ? 62 : 76), $primary, $this->boldFont, $entry->kickoff);
            if ($entry->callTime !== null) {
                imagettftext($image, 17, 0, 88, $y + ($compact ? 96 : 116), $muted, $this->regularFont, 'Conv. '.$entry->callTime);
            }
            imagettftext($image, $compact ? 27 : 31, 0, 290, $y + ($compact ? 46 : 56), $text, $this->boldFont, $this->fit($entry->team, 24));
            imagettftext($image, $compact ? 22 : 25, 0, 290, $y + ($compact ? 82 : 98), $text, $this->regularFont, $this->fit(($entry->home ? 'vs ' : '@ ').$entry->opponent, 27));
            if ($entry->place !== null) {
                imagettftext($image, 17, 0, 290, $y + ($compact ? 108 : 132), $muted, $this->regularFont, $this->fit($entry->place, 40));
            }
            imagettftext($image, 18, 0, LayoutPlanner::WIDTH - 200, $y + (int) ($rowHeight / 2) + 8, $entry->home ? $primary : $muted, $this->boldFont, $entry->home ? 'A CASA' : 'A FORA');
            $y += $rowHeight + LayoutPlanner::ROW_GAP;
        }

        return $this->finish($image, $page->page, $page->totalPages);
    }

    public function renderResults(ResultsPage $page, Branding $branding): string
    {
        [$image, $primary, $white, $text, $muted] = $this->canvas($page->poster->title, $page->poster->dateLabel, $page->poster->subtitle, $branding);

        $rowHeight = LayoutPlanner::resultRow($page->mode);
        $dense = $page->mode === ResultsPage::MODE_DENSE;
        $nameSize = $dense ? 22 : ($page->mode === ResultsPage::MODE_COMPACT ? 25 : 28);
        $scoreSize = $dense ? 28 : 34;
        $y = LayoutPlanner::HEADER;

        foreach ($page->entries as $entry) {
            imagefilledrectangle($image, 56, $y, LayoutPlanner::WIDTH - 56, $y + $rowHeight, $white);
            $baseline = $y + (int) ($rowHeight / 2) + (int) ($nameSize / 3);

            imagettftext($image, $dense ? 13 : 15, 0, 78, $baseline, $entry->home ? $primary : $muted, $this->boldFont, $entry->home ? 'CASA' : 'FORA');
            // Marcador centrat en un oval del color del club.
            $label = $entry->scoreTeam.' - '.$entry->scoreOpponent;
            $box = imagettfbbox($scoreSize, 0, $this->boldFont, $label);
            $labelWidth = $box === false ? 100 : abs($box[2] - $box[0]);
            $pillWidth = max(170, $labelWidth + 50);
            $pillHeight = $dense ? 52 : 70;
            $pillX = (int) ((LayoutPlanner::WIDTH - $pillWidth) / 2) + 40;

            // Els noms es retallen perquè mai no pisin el marcador.
            $team = $this->fitWidth($entry->team, $nameSize, $this->boldFont, $pillX - 160 - 24);
            imagettftext($image, $nameSize, 0, 160, $baseline, $text, $this->boldFont, $team);
            $pillY = $y + (int) (($rowHeight - $pillHeight) / 2);
            imagefilledrectangle($image, $pillX + 20, $pillY, $pillX + $pillWidth - 20, $pillY + $pillHeight, $primary);
            imagefilledellipse($image, $pillX + 20, $pillY + (int) ($pillHeight / 2), $pillHeight, $pillHeight, $primary);
            imagefilledellipse($image, $pillX + $pillWidth - 20, $pillY + (int) ($pillHeight / 2), $pillHeight, $pillHeight, $primary);
            imagettftext($image, $scoreSize, 0, $pillX + (int) (($pillWidth - $labelWidth) / 2), $pillY + (int) ($pillHeight / 2) + (int) ($scoreSize / 2.6), $white, $this->boldFont, $label);

            $opponent = $this->fitWidth($entry->opponent, $nameSize, $this->regularFont, LayoutPlanner::WIDTH - 78 - ($pillX + $pillWidth) - 24);
            $box = imagettfbbox($nameSize, 0, $this->regularFont, $opponent);
            $opponentWidth = $box === false ? 200 : abs($box[2] - $box[0]);
            imagettftext($image, $nameSize, 0, LayoutPlanner::WIDTH - 78 - $opponentWidth, $baseline, $muted, $this->regularFont, $opponent);

            $y += $rowHeight + LayoutPlanner::RESULT_GAP;
        }

        return $this->finish($image, $page->page, $page->totalPages);
    }

    /**
     * Fons en degradat, escut i capçalera comuns a totes les imatges.
     *
     * @return array{GdImage, int, int, int, int}
     */
    private function canvas(string $title, string $dateLabel, string $subtitle, Branding $branding): array
    {
        foreach ([$this->regularFont, $this->boldFont] as $font) {
            if (! is_file($font)) {
                throw new RuntimeException("No es troba la font {$font} (instal·leu fonts-dejavu-core o configureu GD_FONT_REGULAR / GD_FONT_BOLD).");
            }
        }

        $image = imagecreatetruecolor(LayoutPlanner::WIDTH, LayoutPlanner::HEIGHT);
        $primary = $this->color($image, $branding->primaryColor);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $text = (int) imagecolorallocate($image, 15, 31, 56);
        $muted = (int) imagecolorallocate($image, 82, 98, 122);

        [$r1, $g1, $b1] = $this->rgb($branding->primaryColor);
        [$r2, $g2, $b2] = $this->rgb($branding->primaryDarkColor);
        for ($y = 0; $y < LayoutPlanner::HEIGHT; $y++) {
            $t = $y / LayoutPlanner::HEIGHT;
            imageline($image, 0, $y, LayoutPlanner::WIDTH, $y, (int) imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }

        $x = 72;
        if ($branding->logoPng !== null && ($logo = @imagecreatefromstring($branding->logoPng)) !== false) {
            $scale = min(150 / imagesx($logo), 170 / imagesy($logo));
            imagecopyresampled($image, $logo, 72, 70, 0, 0, (int) (imagesx($logo) * $scale), (int) (imagesy($logo) * $scale), imagesx($logo), imagesy($logo));
            $x = 258;
        }

        imagettftext($image, 22, 0, $x, 105, $white, $this->boldFont, mb_strtoupper($branding->clubName));
        imagettftext($image, 78, 0, $x, 215, $white, $this->boldFont, mb_strtoupper($title));
        imagettftext($image, 36, 0, $x, 280, $white, $this->boldFont, $dateLabel);
        if ($subtitle !== '') {
            imagettftext($image, 22, 0, $x, 330, $white, $this->regularFont, $subtitle);
        }

        return [$image, $primary, $white, $text, $muted];
    }

    /** Peu comú: compte del club, número de pàgina (si n'hi ha més d'una) i etiqueta. */
    private function finish(GdImage $image, int $page, int $totalPages): string
    {
        $white = (int) imagecolorallocate($image, 255, 255, 255);

        imagettftext($image, 30, 0, 72, LayoutPlanner::HEIGHT - 60, $white, $this->boldFont, LayoutPlanner::FOOTER_LEFT);
        $box = imagettfbbox(30, 0, $this->boldFont, LayoutPlanner::FOOTER_RIGHT);
        $rightWidth = $box === false ? 300 : abs($box[2] - $box[0]);
        imagettftext($image, 30, 0, LayoutPlanner::WIDTH - 72 - $rightWidth, LayoutPlanner::HEIGHT - 60, $white, $this->boldFont, LayoutPlanner::FOOTER_RIGHT);
        if ($totalPages > 1) {
            imagettftext($image, 22, 0, (int) (LayoutPlanner::WIDTH / 2) - 30, LayoutPlanner::HEIGHT - 60, $white, $this->regularFont, $page.'/'.$totalPages);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** Retalla el text amb «…» fins que cap dins l'amplada indicada. */
    private function fitWidth(string $value, int $size, string $font, int $maxWidth): string
    {
        while (mb_strlen($value) > 1) {
            $box = imagettfbbox($size, 0, $font, $value);
            if ($box === false || abs($box[2] - $box[0]) <= $maxWidth) {
                break;
            }
            $value = rtrim(mb_substr(rtrim($value, '…'), 0, -1)).'…';
        }

        return $value;
    }

    private function fit(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1).'…' : $value;
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function color(GdImage $image, string $hex): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return (int) imagecolorallocate($image, $r, $g, $b);
    }
}
