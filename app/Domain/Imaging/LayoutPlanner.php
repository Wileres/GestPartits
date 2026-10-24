<?php

namespace App\Domain\Imaging;

/**
 * Decideix quants partits caben a cada imatge de 1080 × 1920 sense fer el text il·legible:
 *  - fins a 8 partits: disseny normal (files de 150 px, text de 40 px o més);
 *  - fins a 10: disseny compacte (files de 118 px, text de 32 px o més) i avís;
 *  - més de 10: diverses pàgines del mateix dia (10 per pàgina) i avís.
 * Les alçades són fixes, de manera que el resultat és determinista i es pot provar sense navegador.
 */
final class LayoutPlanner
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    public const HEADER = 400;

    public const FOOTER = 150;

    /** Peu de la imatge: compte del club (esquerra) i etiqueta (dreta). */
    public const FOOTER_LEFT = '@olotjspm';

    public const FOOTER_RIGHT = '#futboldebarri';

    public const NORMAL_ROW = 150;

    public const COMPACT_ROW = 118;

    public const ROW_GAP = 12;

    /** Noms més llargs es retallen amb «…» (es mostra un avís). */
    public const LONG_NAME = 26;

    public static function capacity(string $mode): int
    {
        $available = self::HEIGHT - self::HEADER - self::FOOTER;
        $row = $mode === PosterPage::MODE_NORMAL ? self::NORMAL_ROW : self::COMPACT_ROW;

        return intdiv($available + self::ROW_GAP, $row + self::ROW_GAP);
    }

    public function plan(DayPoster $poster): LayoutPlan
    {
        $count = count($poster->entries);
        $warnings = [];
        $normal = self::capacity(PosterPage::MODE_NORMAL);
        $compact = self::capacity(PosterPage::MODE_COMPACT);

        if ($count <= $normal) {
            $pages = [new PosterPage($poster, $poster->entries, PosterPage::MODE_NORMAL, 1, 1)];
        } elseif ($count <= $compact) {
            $pages = [new PosterPage($poster, $poster->entries, PosterPage::MODE_COMPACT, 1, 1)];
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        } else {
            $chunks = array_chunk($poster->entries, $compact);
            $total = count($chunks);
            $pages = array_map(fn (array $entries, int $i) => new PosterPage($poster, $entries, PosterPage::MODE_COMPACT, $i + 1, $total), $chunks, array_keys($chunks));
            $warnings[] = ['code' => LayoutPlan::WARNING_PAGINATED, 'params' => ['count' => $count, 'pages' => $total]];
        }

        foreach ($poster->entries as $entry) {
            if (mb_strlen($entry->team) > self::LONG_NAME || mb_strlen($entry->opponent) > self::LONG_NAME) {
                $warnings[] = ['code' => LayoutPlan::WARNING_LONG_NAMES, 'params' => ['limit' => self::LONG_NAME]];
                break;
            }
        }

        return new LayoutPlan($pages, $warnings);
    }
}
