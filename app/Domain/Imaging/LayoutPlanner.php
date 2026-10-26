<?php

namespace App\Domain\Imaging;

/**
 * Decideix quants partits caben a cada imatge de 1080 × 1920 sense fer el text il·legible:
 *  - fins a 8 partits: disseny normal (files de 150 px, text de 40 px o més);
 *  - fins a 10: disseny compacte (files de 118 px, text de 32 px o més) i avís;
 *  - més de 10: diverses pàgines del mateix dia (10 per pàgina) i avís.
 * Si els partits van agrupats («A casa» / «A fora»), cada grup afegeix un títol petit que també ocupa alçada;
 * les pàgines s'omplen segons l'alçada real. Les alçades són fixes, de manera que el resultat és determinista
 * i es pot provar sense navegador.
 *
 * Les imatges de resultats segueixen el mateix criteri amb tres densitats (normal, compacte i dens).
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

    /** Alçada del títol petit d'un grup («A casa» / «A fora»). */
    public const HEADING = 52;

    /** Noms més llargs es retallen amb «…» (es mostra un avís). */
    public const LONG_NAME = 26;

    /** Files de la imatge de resultats: normal, compacta i densa. */
    public const RESULT_NORMAL_ROW = 112;

    public const RESULT_COMPACT_ROW = 90;

    public const RESULT_DENSE_ROW = 68;

    public const RESULT_GAP = 10;

    /** A les files de resultats els noms són més estrets. */
    public const RESULT_LONG_NAME = 22;

    /** Alçada disponible per a les files entre la capçalera i el peu. */
    public static function available(): int
    {
        return self::HEIGHT - self::HEADER - self::FOOTER;
    }

    public static function capacity(string $mode): int
    {
        $row = $mode === PosterPage::MODE_NORMAL ? self::NORMAL_ROW : self::COMPACT_ROW;

        return intdiv(self::available() + self::ROW_GAP, $row + self::ROW_GAP);
    }

    public static function resultRow(string $mode): int
    {
        return match ($mode) {
            ResultsPage::MODE_NORMAL => self::RESULT_NORMAL_ROW,
            ResultsPage::MODE_COMPACT => self::RESULT_COMPACT_ROW,
            default => self::RESULT_DENSE_ROW,
        };
    }

    public static function resultCapacity(string $mode): int
    {
        return intdiv(self::available() + self::RESULT_GAP, self::resultRow($mode) + self::RESULT_GAP);
    }

    /**
     * Alçada que ocupen els partits d'una pàgina (files, buits i, si van agrupats, els títols de grup).
     *
     * @param  list<PosterEntry>  $entries
     */
    public static function heightOf(array $entries, string $mode, bool $grouped): int
    {
        $row = $mode === PosterPage::MODE_NORMAL ? self::NORMAL_ROW : self::COMPACT_ROW;
        $height = 0;
        $previous = null;

        foreach ($entries as $index => $entry) {
            if ($grouped && ($index === 0 || $entry->home !== $previous)) {
                $height += self::HEADING + self::ROW_GAP;
            }
            $height += $row + self::ROW_GAP;
            $previous = $entry->home;
        }

        return max(0, $height - self::ROW_GAP);
    }

    public function plan(DayPoster $poster): LayoutPlan
    {
        $count = count($poster->entries);
        $grouped = $poster->grouped;
        $warnings = [];

        if (self::heightOf($poster->entries, PosterPage::MODE_NORMAL, $grouped) <= self::available()) {
            $pages = [new PosterPage($poster, $poster->entries, PosterPage::MODE_NORMAL, 1, 1)];
        } elseif (self::heightOf($poster->entries, PosterPage::MODE_COMPACT, $grouped) <= self::available()) {
            $pages = [new PosterPage($poster, $poster->entries, PosterPage::MODE_COMPACT, 1, 1)];
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        } else {
            $chunks = $this->chunk($poster->entries, $grouped);
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

    /**
     * Resultats de la jornada: una sola imatge sempre que sigui possible (la densitat augmenta amb els partits).
     */
    public function planResults(ResultsPoster $poster): ResultsLayoutPlan
    {
        $count = count($poster->entries);
        $warnings = [];

        if ($count <= self::resultCapacity(ResultsPage::MODE_NORMAL)) {
            $pages = [new ResultsPage($poster, $poster->entries, ResultsPage::MODE_NORMAL, 1, 1)];
        } elseif ($count <= self::resultCapacity(ResultsPage::MODE_COMPACT)) {
            $pages = [new ResultsPage($poster, $poster->entries, ResultsPage::MODE_COMPACT, 1, 1)];
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        } elseif ($count <= self::resultCapacity(ResultsPage::MODE_DENSE)) {
            $pages = [new ResultsPage($poster, $poster->entries, ResultsPage::MODE_DENSE, 1, 1)];
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        } else {
            $chunks = array_chunk($poster->entries, self::resultCapacity(ResultsPage::MODE_DENSE));
            $total = count($chunks);
            $pages = array_map(fn (array $entries, int $i) => new ResultsPage($poster, $entries, ResultsPage::MODE_DENSE, $i + 1, $total), $chunks, array_keys($chunks));
            $warnings[] = ['code' => LayoutPlan::WARNING_PAGINATED, 'params' => ['count' => $count, 'pages' => $total]];
        }

        foreach ($poster->entries as $entry) {
            if (mb_strlen($entry->team) > self::RESULT_LONG_NAME || mb_strlen($entry->opponent) > self::RESULT_LONG_NAME) {
                $warnings[] = ['code' => LayoutPlan::WARNING_LONG_NAMES, 'params' => ['limit' => self::RESULT_LONG_NAME]];
                break;
            }
        }

        return new ResultsLayoutPlan($pages, $warnings);
    }

    /**
     * Reparteix els partits en pàgines compactes segons l'alçada real (un grup que continua a la pàgina següent
     * hi repeteix el títol).
     *
     * @param  list<PosterEntry>  $entries
     * @return list<list<PosterEntry>>
     */
    private function chunk(array $entries, bool $grouped): array
    {
        $pages = [];
        $current = [];

        foreach ($entries as $entry) {
            $candidate = [...$current, $entry];

            if ($current !== [] && self::heightOf($candidate, PosterPage::MODE_COMPACT, $grouped) > self::available()) {
                $pages[] = $current;
                $current = [$entry];
            } else {
                $current = $candidate;
            }
        }

        if ($current !== []) {
            $pages[] = $current;
        }

        return $pages;
    }
}
