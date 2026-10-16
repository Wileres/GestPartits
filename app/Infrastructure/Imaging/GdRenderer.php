<?php

namespace App\Infrastructure\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterPage;
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
        foreach ([$this->regularFont, $this->boldFont] as $font) {
            if (! is_file($font)) {
                throw new RuntimeException("No es troba la font {$font} (instal·leu fonts-dejavu-core o configureu GD_FONT_REGULAR / GD_FONT_BOLD).");
            }
        }

        $image = imagecreatetruecolor(LayoutPlanner::WIDTH, LayoutPlanner::HEIGHT);
        $primary = $this->color($image, $branding->primaryColor);
        $dark = $this->color($image, $branding->primaryDarkColor);
        $white = imagecolorallocate($image, 255, 255, 255);
        $text = imagecolorallocate($image, 15, 31, 56);
        $muted = imagecolorallocate($image, 82, 98, 122);

        // Fons en degradat vertical.
        [$r1, $g1, $b1] = $this->rgb($branding->primaryColor);
        [$r2, $g2, $b2] = $this->rgb($branding->primaryDarkColor);
        for ($y = 0; $y < LayoutPlanner::HEIGHT; $y++) {
            $t = $y / LayoutPlanner::HEIGHT;
            imageline($image, 0, $y, LayoutPlanner::WIDTH, $y, imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }

        $x = 72;
        if ($branding->logoPng !== null && ($logo = @imagecreatefromstring($branding->logoPng)) !== false) {
            $scale = min(150 / imagesx($logo), 170 / imagesy($logo));
            imagecopyresampled($image, $logo, 72, 70, 0, 0, (int) (imagesx($logo) * $scale), (int) (imagesy($logo) * $scale), imagesx($logo), imagesy($logo));
            $x = 258;
        }

        imagettftext($image, 22, 0, $x, 105, $white, $this->boldFont, mb_strtoupper($branding->clubName));
        imagettftext($image, 78, 0, $x, 215, $white, $this->boldFont, mb_strtoupper($page->poster->title));
        imagettftext($image, 36, 0, $x, 280, $white, $this->boldFont, $page->poster->dateLabel);
        if ($page->poster->subtitle !== '') {
            imagettftext($image, 22, 0, $x, 330, $white, $this->regularFont, $page->poster->subtitle);
        }

        $compact = $page->mode === PosterPage::MODE_COMPACT;
        $rowHeight = $compact ? LayoutPlanner::COMPACT_ROW : LayoutPlanner::NORMAL_ROW;
        $y = LayoutPlanner::HEADER;

        foreach ($page->entries as $entry) {
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

        imagettftext($image, 26, 0, 72, LayoutPlanner::HEIGHT - 60, $white, $this->boldFont, 'Som-hi, Joventut!');
        if ($page->totalPages > 1) {
            imagettftext($image, 22, 0, LayoutPlanner::WIDTH - 150, LayoutPlanner::HEIGHT - 60, $white, $this->regularFont, $page->page.'/'.$page->totalPages);
        }

        unset($dark);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
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
