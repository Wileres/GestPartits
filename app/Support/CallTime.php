<?php

namespace App\Support;

/**
 * Hora de convocatòria per defecte (partits a casa i a fora): una hora abans del partit.
 */
final class CallTime
{
    public const OFFSET_MINUTES = 60;

    /**
     * «11:15» → «10:15:00» (null si no hi ha hora de partit).
     */
    public static function default(?string $kickoff): ?string
    {
        if ($kickoff === null || $kickoff === '') {
            return null;
        }

        return Time::fromMinutes(max(0, Time::toMinutes($kickoff) - self::OFFSET_MINUTES)).':00';
    }

    /**
     * Convocatòria d'un partit quan canvia l'hora d'inici:
     *  - sense convocatòria, es proposa la per defecte;
     *  - si era la per defecte de l'hora anterior, la segueix;
     *  - si el gestor n'ha posat una de pròpia, es respecta.
     */
    public static function follow(?string $call, ?string $oldKickoff, ?string $newKickoff): ?string
    {
        if ($newKickoff === null || $newKickoff === '') {
            return $call;
        }

        if ($call === null || $call === '') {
            return self::default($newKickoff);
        }

        if ($oldKickoff !== null && Time::toMinutes($call) === Time::toMinutes((string) self::default($oldKickoff))) {
            return self::default($newKickoff);
        }

        return $call;
    }
}
