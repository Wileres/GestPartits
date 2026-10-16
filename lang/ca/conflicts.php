<?php

/*
| Textos dels conflictes de la programació (mateixos codis que el domini i el validador del calendari).
*/

return [
    'F7_CAPACITY_EXCEEDED' => 'Hi ha massa partits de futbol 7 alhora: :teams.',
    'F11_OVERLAPS_F7' => 'Un partit de futbol 11 coincideix amb futbol 7: :teams.',
    'F11_OVERLAPS_F11' => 'Dos partits de futbol 11 coincideixen: :teams.',
    'AREA_OVERLAP' => 'El camp ja està ocupat: :teams.',
    'F11_REQUIRES_FULL_FIELD' => ':teams: el futbol 11 necessita el camp complet.',
    'F7_ON_FULL_FIELD' => ':teams: partit de futbol 7 ocupant el camp complet.',
    'OUTSIDE_OPERATING_HOURS' => ':teams: l\'hora ha d\'estar entre les :from i les :to.',
    'OFF_GRID' => ':teams: l\'hora ha de ser en punt, i quart, i mitja o menys quart.',
    'OUTSIDE_SLOT' => ':teams: fora de les franges habituals.',
    'OUTSIDE_MATCHDAY' => ':teams: la data és fora de la jornada.',
    'FIELD_BLOCKED' => ':teams: camp bloquejat (:reason).',
    'DUPLICATE_TEAM' => 'L\'equip té més d\'un partit en aquesta jornada: :teams.',
    'CALL_AFTER_KICKOFF' => ':teams: la convocatòria és posterior a l\'hora del partit.',
    'MANDATORY_RULE_BROKEN' => ':teams: regla obligatòria incomplerta (:rule).',
    'RECOMMENDED_RULE_NOT_MET' => ':teams: no s\'ha pogut respectar l\'horari preferent (:rule).',
    'HOME_MATCH_UNSCHEDULED' => ':teams: partit a casa sense horari.',
    'AWAY_MISSING_DATA' => ':teams: falten dades del partit a fora (data, hora o instal·lació).',

    'override' => 'Excepció acceptada: :reason',
    'not_before' => 'no abans de :time',
    'not_after' => 'no després de :time',

    'unplaced' => [
        'no_slot' => 'Cap franja admet aquest partit (franges, finestres horàries o bloquejos).',
        'field_full' => 'Tots els horaris compatibles ja estan ocupats.',
    ],
];
