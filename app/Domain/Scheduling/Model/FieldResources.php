<?php

namespace App\Domain\Scheduling\Model;

/**
 * El camp es modela amb dos recursos (les dues meitats). Un partit de futbol 7 n'ocupa una
 * i un de futbol 11, totes dues: així les quatre regles de capacitat es redueixen a «cap
 * recurs no pot tenir dues ocupacions solapades».
 */
final class FieldResources
{
    public const CAMP_1 = 'camp_1';

    public const CAMP_2 = 'camp_2';

    public const FULL = 'full';

    public const HALVES = [self::CAMP_1, self::CAMP_2];

    /** Ordre de preferència dels espais quan hi ha empat. */
    public const ORDER = [self::CAMP_1 => 0, self::CAMP_2 => 1, self::FULL => 2];

    /**
     * Recursos que ocupa un partit segons la modalitat i l'espai assignat.
     *
     * @return list<string>
     */
    public static function for(string $format, ?string $area): array
    {
        if ($format === MatchItem::FORMAT_F11 || $area === self::FULL || $area === null) {
            return self::HALVES;
        }

        return [$area];
    }

    /**
     * Espais on la proposta pot col·locar un partit (el futbol 7 mai ocupa el camp complet automàticament).
     *
     * @return list<string>
     */
    public static function proposalAreas(string $format): array
    {
        return $format === MatchItem::FORMAT_F11 ? [self::FULL] : self::HALVES;
    }
}
