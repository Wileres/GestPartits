<?php

namespace App\Infrastructure\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterFormat;
use App\Domain\Imaging\PosterPage;
use App\Domain\Imaging\ResultsPage;
use App\Domain\Imaging\RowMetrics;
use App\Domain\Imaging\ScheduleImageRenderer;
use GdImage;
use RuntimeException;

/**
 * Alternativa sense Chromium: dibuixa una versió més sòbria de la imatge amb GD i fonts TTF del sistema.
 * Fa servir les mateixes mides de fila (`RowMetrics`) que Chromium, de manera que les caixes s'estiren igual.
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
        $format = $page->poster->format;
        [$image, $primary, $white, $text, $muted] = $this->canvas($format, $page->poster->title, $page->poster->dateLabel, $page->poster->subtitle, $branding);

        $m = RowMetrics::day($page->rowHeight);
        $rowHeight = $m->row;
        $width = $format->width();
        $y = $format->header();
        $previousHome = null;

        foreach ($page->entries as $index => $entry) {
            // Títol petit de cada grup («A casa» / «A fora»).
            if ($page->poster->grouped && ($index === 0 || $entry->home !== $previousHome)) {
                imagettftext($image, 24, 0, 68, $y + 36, $white, $this->boldFont, $entry->home ? 'A CASA' : 'A FORA');
                imagefilledrectangle($image, 230, $y + 25, $width - 68, $y + 27, $white);
                $y += LayoutPlanner::HEADING + LayoutPlanner::ROW_GAP;
            }
            $previousHome = $entry->home;

            imagefilledrectangle($image, 56, $y, $width - 56, $y + $rowHeight, $white);
            $middle = $y + (int) ($rowHeight / 2);
            $textX = 56 + 32 + $m->timeWidth + 20;
            $badgeX = $width - 56 - 24 - $m->badgeWidth;
            $badge = $entry->home ? 'A CASA' : 'A FORA';

            // Hora (i convocatòria) centrades a la columna de l'hora; el punt de partida del text és la línia de base.
            $timeSize = $m->time * 0.75;
            $withCall = $m->density === RowMetrics::FULL && $entry->callTime !== null;
            $this->centered($image, $timeSize, $withCall ? $middle - 2 : $middle + (int) ($timeSize * 0.36), $primary, $this->boldFont, $entry->kickoff, 56 + 32, $m->timeWidth);
            if ($withCall) {
                $this->centered($image, $m->call * 0.75, $middle + (int) ($m->call * 1.1), $muted, $this->regularFont, 'Conv. '.$entry->callTime, 56 + 32, $m->timeWidth);
            }

            if ($m->density === RowMetrics::TIGHT) {
                $teamSize = $m->team * 0.75;
                $team = $this->fitWidth($entry->team, (int) $teamSize, $this->boldFont, 330);
                $baseline = $middle + (int) ($teamSize * 0.35);
                imagettftext($image, $teamSize, 0, $textX, $baseline, $text, $this->boldFont, $team);
                $teamWidth = $this->width($team, $teamSize, $this->boldFont);
                $opponent = $this->fitWidth(($entry->home ? 'vs ' : '@ ').$entry->opponent, (int) ($m->opponent * 0.75), $this->regularFont, max(80, $badgeX - ($textX + $teamWidth + 24) - 12));
                imagettftext($image, $m->opponent * 0.75, 0, $textX + $teamWidth + 16, $baseline, $text, $this->regularFont, $opponent);
            } else {
                $teamSize = $m->team * 0.75;
                $opponentSize = $m->opponent * 0.75;
                $room = $badgeX - $textX - 16;
                $lines = $m->density === RowMetrics::FULL && $entry->place !== null ? 3 : 2;
                $block = $teamSize + $opponentSize + ($lines === 3 ? $m->place * 0.75 : 0) + 14 * ($lines - 1);
                $top = $middle - (int) ($block / 2);
                imagettftext($image, $teamSize, 0, $textX, $top + (int) ($teamSize * 1.0), $text, $this->boldFont, $this->fitWidth($entry->team, (int) $teamSize, $this->boldFont, $room));
                imagettftext($image, $opponentSize, 0, $textX, $top + (int) ($teamSize + 12 + $opponentSize), $text, $this->regularFont, $this->fitWidth(($entry->home ? 'vs ' : '@ ').$entry->opponent, (int) $opponentSize, $this->regularFont, $room));
                if ($lines === 3) {
                    imagettftext($image, $m->place * 0.75, 0, $textX, $top + (int) ($teamSize + $opponentSize + $m->place * 0.75 + 26), $muted, $this->regularFont, $this->fitWidth((string) $entry->place, (int) ($m->place * 0.75), $this->regularFont, $room));
                }
            }

            $badgeSize = $m->badgeFont * 0.75;
            imagettftext($image, $badgeSize, 0, $badgeX + (int) (($m->badgeWidth - $this->width($badge, $badgeSize, $this->boldFont)) / 2), $middle + (int) ($badgeSize * 0.4), $entry->home ? $primary : $muted, $this->boldFont, $badge);
            $y += $rowHeight + LayoutPlanner::ROW_GAP;
        }

        return $this->finish($image, $format, $page->page, $page->totalPages);
    }

    public function renderResults(ResultsPage $page, Branding $branding): string
    {
        $format = $page->poster->format;
        [$image, $primary, $white, $text, $muted] = $this->canvas($format, $page->poster->title, $page->poster->dateLabel, $page->poster->subtitle, $branding);

        $m = RowMetrics::result($page->rowHeight);
        $width = $format->width();
        $nameSize = $m->team * 0.75;
        $scoreSize = $m->time * 0.75;
        $y = $format->header();

        foreach ($page->entries as $entry) {
            imagefilledrectangle($image, 56, $y, $width - 56, $y + $m->row, $white);
            $baseline = $y + (int) ($m->row / 2) + (int) ($nameSize * 0.35);

            imagettftext($image, $m->badgeFont * 0.75, 0, 78, $baseline, $entry->home ? $primary : $muted, $this->boldFont, $entry->home ? 'CASA' : 'FORA');

            // Marcador centrat en un oval del color del club.
            $label = $entry->scoreTeam.' - '.$entry->scoreOpponent;
            $labelWidth = $this->width($label, $scoreSize, $this->boldFont);
            $pillWidth = max($m->timeWidth, $labelWidth + 50);
            $pillHeight = (int) round($m->row * 0.68);
            $pillX = (int) (($width - $pillWidth) / 2) + 40;
            $pillY = $y + (int) (($m->row - $pillHeight) / 2);
            $radius = (int) ($pillHeight / 2);
            imagefilledrectangle($image, $pillX + $radius, $pillY, $pillX + $pillWidth - $radius, $pillY + $pillHeight, $primary);
            imagefilledellipse($image, $pillX + $radius, $pillY + $radius, $pillHeight, $pillHeight, $primary);
            imagefilledellipse($image, $pillX + $pillWidth - $radius, $pillY + $radius, $pillHeight, $pillHeight, $primary);
            imagettftext($image, $scoreSize, 0, $pillX + (int) (($pillWidth - $labelWidth) / 2), $pillY + $radius + (int) ($scoreSize * 0.36), $white, $this->boldFont, $label);

            // Els noms es retallen perquè mai no pisin el marcador.
            $team = $this->fitWidth($entry->team, (int) $nameSize, $this->boldFont, $pillX - 160 - 24);
            imagettftext($image, $nameSize, 0, 160, $baseline, $text, $this->boldFont, $team);
            $opponent = $this->fitWidth($entry->opponent, (int) $nameSize, $this->regularFont, $width - 78 - ($pillX + $pillWidth) - 24);
            imagettftext($image, $nameSize, 0, $width - 78 - $this->width($opponent, $nameSize, $this->regularFont), $baseline, $muted, $this->regularFont, $opponent);

            $y += $m->row + LayoutPlanner::RESULT_GAP;
        }

        return $this->finish($image, $format, $page->page, $page->totalPages);
    }

    /**
     * Fons en degradat, escut i capçalera comuns a totes les imatges.
     *
     * @return array{GdImage, int, int, int, int}
     */
    private function canvas(PosterFormat $format, string $title, string $dateLabel, string $subtitle, Branding $branding): array
    {
        foreach ([$this->regularFont, $this->boldFont] as $font) {
            if (! is_file($font)) {
                throw new RuntimeException("No es troba la font {$font} (instal·leu fonts-dejavu-core o configureu GD_FONT_REGULAR / GD_FONT_BOLD).");
            }
        }

        $k = $format->scale();
        $image = imagecreatetruecolor($format->width(), $format->height());
        $primary = $this->color($image, $branding->primaryColor);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $text = (int) imagecolorallocate($image, 15, 31, 56);
        $muted = (int) imagecolorallocate($image, 82, 98, 122);

        [$r1, $g1, $b1] = $this->rgb($branding->primaryColor);
        [$r2, $g2, $b2] = $this->rgb($branding->primaryDarkColor);
        for ($y = 0; $y < $format->height(); $y++) {
            $t = $y / $format->height();
            imageline($image, 0, $y, $format->width(), $y, (int) imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }

        $x = 72;
        if ($branding->logoPng !== null && ($logo = @imagecreatefromstring($branding->logoPng)) !== false) {
            $scale = min(150 * $k / imagesx($logo), 170 * $k / imagesy($logo));
            imagecopyresampled($image, $logo, 72, (int) (70 * $k), 0, 0, (int) (imagesx($logo) * $scale), (int) (imagesy($logo) * $scale), imagesx($logo), imagesy($logo));
            $x = (int) (72 + 150 * $k + 36 * $k);
        }

        imagettftext($image, 22 * $k, 0, $x, (int) (105 * $k), $white, $this->boldFont, mb_strtoupper($branding->clubName));
        imagettftext($image, 78 * $k, 0, $x, (int) (215 * $k), $white, $this->boldFont, mb_strtoupper($title));
        imagettftext($image, 36 * $k, 0, $x, (int) (280 * $k), $white, $this->boldFont, $dateLabel);
        if ($subtitle !== '') {
            imagettftext($image, 22 * $k, 0, $x, (int) (330 * $k), $white, $this->regularFont, $subtitle);
        }

        return [$image, $primary, $white, $text, $muted];
    }

    /** Peu comú: compte del club, número de pàgina (si n'hi ha més d'una) i etiqueta. */
    private function finish(GdImage $image, PosterFormat $format, int $page, int $totalPages): string
    {
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $size = $format === PosterFormat::Story ? 30 : 25;
        $baseline = $format->height() - (int) ($format->footer() * 0.4);

        imagettftext($image, $size, 0, 72, $baseline, $white, $this->boldFont, LayoutPlanner::FOOTER_LEFT);
        imagettftext($image, $size, 0, $format->width() - 72 - $this->width(LayoutPlanner::FOOTER_RIGHT, $size, $this->boldFont), $baseline, $white, $this->boldFont, LayoutPlanner::FOOTER_RIGHT);
        if ($totalPages > 1) {
            imagettftext($image, 22, 0, (int) ($format->width() / 2) - 30, $baseline, $white, $this->regularFont, $page.'/'.$totalPages);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** Amplada d'un text amb la mida i la font indicades. */
    private function width(string $value, float $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $value);

        return $box === false ? 0 : abs($box[2] - $box[0]);
    }

    /** Dibuixa un text centrat en una zona d'amplada `$areaWidth` que comença a `$left` (o l'amplada del text si no s'indica). */
    private function centered(GdImage $image, float $size, int $baseline, int $color, string $font, string $value, int $left, ?int $areaWidth = null): void
    {
        $textWidth = $this->width($value, $size, $font);
        $area = $areaWidth ?? $textWidth;
        imagettftext($image, $size, 0, $left + (int) (($area - $textWidth) / 2), $baseline, $color, $font, $value);
    }

    /** Retalla el text amb «…» fins que cap dins l'amplada indicada. */
    private function fitWidth(string $value, int $size, string $font, int $maxWidth): string
    {
        while (mb_strlen($value) > 1 && $this->width($value, $size, $font) > $maxWidth) {
            $value = rtrim(mb_substr(rtrim($value, '…'), 0, -1)).'…';
        }

        return $value;
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
