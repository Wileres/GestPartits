<?php

namespace App\Support;

/**
 * Hora de convocatòria per defecte: una hora abans del partit. A futbol 7 és 45 minuts abans als partits a casa
 * i 1 hora i 45 minuts abans als partits a fora.
 */
final class CallTime
{
    public const OFFSET_MINUTES = 60;

    public const FOOTBALL7_HOME_OFFSET_MINUTES = 45;

    public const FOOTBALL7_AWAY_OFFSET_MINUTES = 105;

    /**
     * Minuts d'antelació per defecte segons la modalitat («f7», «f11») i la condició («home», «away»).
     */
    public static function offset(?string $format, ?string $homeAway): int
    {
        if ($format === 'f7') {
            return match ($homeAway) {
                'home' => self::FOOTBALL7_HOME_OFFSET_MINUTES,
                'away' => self::FOOTBALL7_AWAY_OFFSET_MINUTES,
                default => self::OFFSET_MINUTES,
            };
        }

        return self::OFFSET_MINUTES;
    }

    /**
     * «11:15» → «10:15:00» (null si no hi ha hora de partit).
     */
    public static function default(?string $kickoff, ?string $format = null, ?string $homeAway = null): ?string
    {
        if ($kickoff === null || $kickoff === '') {
            return null;
        }

        return Time::fromMinutes(max(0, Time::toMinutes($kickoff) - self::offset($format, $homeAway))).':00';
    }

    /**
     * Convocatòria d'un partit quan canvia l'hora d'inici (o la modalitat o la condició a casa / a fora):
     *  - sense convocatòria, es proposa la per defecte;
     *  - si era la per defecte d'abans, la segueix;
     *  - si el gestor n'ha posat una de pròpia, es respecta.
     *
     * @param  string|null  $oldFormat  modalitat anterior (si no s'indica, la mateixa)
     * @param  string|null  $oldHomeAway  condició anterior (si no s'indica, la mateixa)
     */
    public static function follow(?string $call, ?string $oldKickoff, ?string $newKickoff, ?string $format = null, ?string $homeAway = null, ?string $oldFormat = null, ?string $oldHomeAway = null): ?string
    {
        if ($newKickoff === null || $newKickoff === '') {
            return $call;
        }

        if ($call === null || $call === '') {
            return self::default($newKickoff, $format, $homeAway);
        }

        $previous = self::default($oldKickoff, $oldFormat ?? $format, $oldHomeAway ?? $homeAway);
        if ($previous !== null && Time::toMinutes($call) === Time::toMinutes($previous)) {
            return self::default($newKickoff, $format, $homeAway);
        }

        return $call;
    }
}
