<?php

namespace App\Domain\Imaging;

/**
 * Mides del text d'una fila segons l'alçada de la caixa. Les files s'estiren per omplir la imatge i el text creix o
 * es redueix amb elles; quan la fila és baixa es treuen línies (primer la instal·lació i la convocatòria, després
 * es posa l'equip i el rival en una sola línia). Les fan servir tant Chromium com GD.
 */
final readonly class RowMetrics
{
    public const FULL = 'full';

    public const MEDIUM = 'medium';

    public const TIGHT = 'tight';

    public function __construct(
        public string $density,
        public int $row,
        public int $time,
        public int $call,
        public int $team,
        public int $opponent,
        public int $place,
        public int $timeWidth,
        public int $radius,
        public int $badgeWidth,
        public int $badgeFont,
        public int $icon,
    ) {}

    private static function clamp(float $value, int $min, int $max): int
    {
        return (int) max($min, min($max, round($value)));
    }

    /** Fila d'un partit del calendari. */
    public static function day(int $row): self
    {
        $radius = self::clamp($row * 0.19, 14, 30);

        if ($row >= 118) {
            return new self(self::FULL, $row, self::clamp($row * 0.41, 50, 80), self::clamp($row * 0.16, 22, 30), self::clamp($row * 0.28, 36, 54), self::clamp($row * 0.227, 30, 44), self::clamp($row * 0.165, 22, 30), self::clamp($row * 1.2, 150, 230), $radius, 190, 22, self::clamp($row * 0.4, 50, 70));
        }

        if ($row >= 84) {
            return new self(self::MEDIUM, $row, self::clamp($row * 0.5, 38, 56), 0, self::clamp($row * 0.34, 26, 40), self::clamp($row * 0.27, 22, 32), 0, 150, $radius, 160, 18, 0);
        }

        return new self(self::TIGHT, $row, self::clamp($row * 0.5, 30, 40), 0, self::clamp($row * 0.42, 24, 34), self::clamp($row * 0.36, 20, 30), 0, 130, $radius, 140, 16, 0);
    }

    /** Fila d'un resultat. */
    public static function result(int $row): self
    {
        return new self(
            self::FULL, $row, self::clamp($row * 0.47, 26, 56), 0, self::clamp($row * 0.33, 22, 40), self::clamp($row * 0.33, 22, 40), 0,
            self::clamp($row * 1.7, 150, 190), self::clamp($row * 0.22, 14, 26), self::clamp($row * 0.8, 70, 90), self::clamp($row * 0.17, 13, 20), 0,
        );
    }
}
