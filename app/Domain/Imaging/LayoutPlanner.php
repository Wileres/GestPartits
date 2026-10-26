<?php

namespace App\Domain\Imaging;

/**
 * Decideix quants partits caben a cada imatge sense fer el text il·legible. El format pot ser una història
 * (1080 × 1920) o una publicació d'Instagram (1080 × 1350).
 *
 * Disseny normal i compacte (per defecte):
 *  - fins a 8 partits d'una història: disseny normal (files de 150 px, text de 40 px o més);
 *  - fins a 10: disseny compacte (files de 118 px, text de 32 px o més) i avís;
 *  - més partits: diverses pàgines del mateix dia.
 *
 * Mode comprimit: tots els partits d'un dia en una sola imatge. L'alçada de les caixes és proporcional al nombre de
 * partits (omplen tot l'espai disponible, entre un mínim i un màxim) i només es reparteix en pàgines si no hi caben
 * ni a l'alçada mínima.
 *
 * Si els partits van agrupats («A casa» / «A fora»), cada grup afegeix un títol petit que també ocupa alçada.
 * Les alçades són fixes, de manera que el resultat és determinista i es pot provar sense navegador.
 */
final class LayoutPlanner
{
    /** Mides de la història (el format per defecte). */
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

    /** Alçades mínima i màxima de les caixes en mode comprimit. */
    public const MIN_FILL_ROW = 64;

    public const MAX_FILL_ROW = 190;

    /** Alçada del títol petit d'un grup («A casa» / «A fora»). */
    public const HEADING = 52;

    /** Noms més llargs es retallen amb «…» (es mostra un avís). */
    public const LONG_NAME = 26;

    /** Files de la imatge de resultats: separació i alçades mínima i màxima (s'estiren per omplir la imatge). */
    public const RESULT_GAP = 10;

    public const RESULT_MIN_ROW = 50;

    public const RESULT_MAX_ROW = 130;

    /** En format història hi ha més alçada: les files poden créixer més abans de deixar espai lliure. */
    public const RESULT_MAX_ROW_STORY = 190;

    /** Per sota d'aquesta alçada s'avisa que s'ha fet servir un disseny dens. */
    public const RESULT_COMFORT_ROW = 80;

    /** A les files de resultats els noms són més estrets. */
    public const RESULT_LONG_NAME = 22;

    /** Alçada disponible per a les files entre la capçalera i el peu. */
    public static function available(PosterFormat $format = PosterFormat::Story): int
    {
        return $format->available();
    }

    public static function capacity(string $mode, PosterFormat $format = PosterFormat::Story): int
    {
        $row = $mode === PosterPage::MODE_NORMAL ? self::NORMAL_ROW : self::COMPACT_ROW;

        return intdiv($format->available() + self::ROW_GAP, $row + self::ROW_GAP);
    }

    /** Quantes files de resultats caben com a màxim (a l'alçada mínima). */
    public static function resultCapacity(PosterFormat $format = PosterFormat::Post): int
    {
        return intdiv($format->available() + self::RESULT_GAP, self::RESULT_MIN_ROW + self::RESULT_GAP);
    }

    /**
     * Alçada que ocupen els partits d'una pàgina amb el disseny normal o compacte.
     *
     * @param  list<PosterEntry>  $entries
     */
    public static function heightOf(array $entries, string $mode, bool $grouped): int
    {
        return self::heightWithRow($entries, $mode === PosterPage::MODE_NORMAL ? self::NORMAL_ROW : self::COMPACT_ROW, $grouped);
    }

    /**
     * Alçada que ocupen els partits amb files d'una alçada concreta (files, buits i, si van agrupats, els títols).
     *
     * @param  list<PosterEntry>  $entries
     */
    public static function heightWithRow(array $entries, int $row, bool $grouped): int
    {
        $count = count($entries);
        if ($count === 0) {
            return 0;
        }

        $headings = self::headings($entries, $grouped);

        return $count * $row + $headings * self::HEADING + ($count + $headings - 1) * self::ROW_GAP;
    }

    /**
     * Nombre de títols de grup («A casa» / «A fora») d'una pàgina.
     *
     * @param  list<PosterEntry>  $entries
     */
    public static function headings(array $entries, bool $grouped): int
    {
        if (! $grouped) {
            return 0;
        }

        $headings = 0;
        $previous = null;
        foreach ($entries as $index => $entry) {
            if ($index === 0 || $entry->home !== $previous) {
                $headings++;
            }
            $previous = $entry->home;
        }

        return $headings;
    }

    /**
     * Alçada de les caixes perquè els partits omplin tot l'espai, entre el mínim i el màxim.
     *
     * @param  list<PosterEntry>  $entries
     */
    public static function fillRow(array $entries, bool $grouped, PosterFormat $format): int
    {
        $count = count($entries);
        if ($count === 0) {
            return self::MAX_FILL_ROW;
        }

        $headings = self::headings($entries, $grouped);
        $row = intdiv($format->available() - $headings * self::HEADING - ($count + $headings - 1) * self::ROW_GAP, $count);

        return max(self::MIN_FILL_ROW, min(self::MAX_FILL_ROW, $row));
    }

    public function plan(DayPoster $poster): LayoutPlan
    {
        $count = count($poster->entries);
        $grouped = $poster->grouped;
        $available = $poster->format->available();
        $warnings = [];

        if ($poster->compress) {
            $pages = $this->planFilled($poster, $warnings);
        } elseif (self::heightOf($poster->entries, PosterPage::MODE_NORMAL, $grouped) <= $available) {
            $pages = [new PosterPage($poster, $poster->entries, PosterPage::MODE_NORMAL, 1, 1, self::NORMAL_ROW)];
        } elseif (self::heightOf($poster->entries, PosterPage::MODE_COMPACT, $grouped) <= $available) {
            $pages = [new PosterPage($poster, $poster->entries, PosterPage::MODE_COMPACT, 1, 1, self::COMPACT_ROW)];
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        } else {
            $chunks = $this->chunk($poster->entries, $grouped, self::COMPACT_ROW, $available);
            $total = count($chunks);
            $pages = array_map(fn (array $entries, int $i) => new PosterPage($poster, $entries, PosterPage::MODE_COMPACT, $i + 1, $total, self::COMPACT_ROW), $chunks, array_keys($chunks));
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
     * Resultats de la jornada: les files s'estiren per omplir la imatge. Només es reparteixen en pàgines si no hi
     * caben ni a l'alçada mínima (les pàgines s'equilibren perquè totes tinguin un nombre de files semblant).
     */
    public function planResults(ResultsPoster $poster): ResultsLayoutPlan
    {
        $count = count($poster->entries);
        $capacity = self::resultCapacity($poster->format);
        $warnings = [];

        if ($count <= $capacity) {
            $chunks = [$poster->entries];
        } else {
            $chunks = array_chunk($poster->entries, (int) ceil($count / ceil($count / $capacity)));
            $warnings[] = ['code' => LayoutPlan::WARNING_PAGINATED, 'params' => ['count' => $count, 'pages' => count($chunks)]];
        }

        $total = count($chunks);
        $pages = [];
        foreach ($chunks as $index => $entries) {
            $pages[] = new ResultsPage($poster, $entries, $this->resultRow(count($entries), $poster->format), $index + 1, $total);
        }

        if ($pages !== [] && $pages[0]->rowHeight < self::RESULT_COMFORT_ROW && $total === 1) {
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        }

        foreach ($poster->entries as $entry) {
            if (mb_strlen($entry->team) > self::RESULT_LONG_NAME || mb_strlen($entry->opponent) > self::RESULT_LONG_NAME) {
                $warnings[] = ['code' => LayoutPlan::WARNING_LONG_NAMES, 'params' => ['limit' => self::RESULT_LONG_NAME]];
                break;
            }
        }

        return new ResultsLayoutPlan($pages, $warnings);
    }

    /** Alçada de les files de resultats perquè en omplin tot l'espai. */
    public function resultRow(int $count, PosterFormat $format): int
    {
        if ($count === 0) {
            return self::RESULT_MAX_ROW;
        }

        $row = intdiv($format->available() - ($count - 1) * self::RESULT_GAP, $count);

        $max = $format === PosterFormat::Story ? self::RESULT_MAX_ROW_STORY : self::RESULT_MAX_ROW;

        return max(self::RESULT_MIN_ROW, min($max, $row));
    }

    /**
     * Mode comprimit: una sola pàgina amb les files estirades; si no hi caben ni a l'alçada mínima, diverses
     * pàgines (cadascuna estirada pel seu compte).
     *
     * @param  list<array{code: string, params: array<string, int|string>}>  $warnings
     * @return list<PosterPage>
     */
    private function planFilled(DayPoster $poster, array &$warnings): array
    {
        $available = $poster->format->available();
        $count = count($poster->entries);

        if (self::heightWithRow($poster->entries, self::MIN_FILL_ROW, $poster->grouped) <= $available) {
            $chunks = [$poster->entries];
        } else {
            $chunks = $this->chunk($poster->entries, $poster->grouped, self::MIN_FILL_ROW, $available);
            $warnings[] = ['code' => LayoutPlan::WARNING_PAGINATED, 'params' => ['count' => $count, 'pages' => count($chunks)]];
        }

        $total = count($chunks);
        $pages = [];
        foreach ($chunks as $index => $entries) {
            $pages[] = new PosterPage($poster, $entries, PosterPage::MODE_FILL, $index + 1, $total, self::fillRow($entries, $poster->grouped, $poster->format));
        }

        if ($total === 1 && $pages[0]->rowHeight < self::COMPACT_ROW) {
            $warnings[] = ['code' => LayoutPlan::WARNING_COMPACT, 'params' => ['count' => $count]];
        }

        return $pages;
    }

    /**
     * Reparteix els partits en pàgines segons l'alçada real a una alçada de fila donada. Els partits de casa i els de
     * fora no es barregen a la mateixa pàgina (un grup que continua a la pàgina següent hi repeteix el títol).
     *
     * @param  list<PosterEntry>  $entries
     * @return list<list<PosterEntry>>
     */
    private function chunk(array $entries, bool $grouped, int $row, int $available): array
    {
        // Si no hi caben tots, els de casa i els de fora van a imatges diferents.
        $home = array_values(array_filter($entries, fn (PosterEntry $e) => $e->home));
        $away = array_values(array_filter($entries, fn (PosterEntry $e) => ! $e->home));

        if ($home !== [] && $away !== []) {
            return [...$this->chunk($home, $grouped, $row, $available), ...$this->chunk($away, $grouped, $row, $available)];
        }

        $pages = [];
        $current = [];

        foreach ($entries as $entry) {
            $candidate = [...$current, $entry];

            if ($current !== [] && self::heightWithRow($candidate, $row, $grouped) > $available) {
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
