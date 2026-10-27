<?php

/*
| Missatges propis de l'aplicació.
*/

return [

    'http' => [
        'default' => 'S\'ha produït un error. Torna-ho a provar.',
        '400' => 'La petició no és vàlida.',
        '401' => 'Cal iniciar la sessió.',
        '403' => 'No tens permís per fer aquesta acció.',
        '404' => 'No s\'ha trobat el recurs demanat.',
        '405' => 'Aquesta operació no està permesa.',
        '409' => 'Algú altre ha modificat aquestes dades. Recarrega-les i torna-ho a provar.',
        '419' => 'La sessió ha caducat. Recarrega la pàgina i torna-ho a provar.',
        '423' => 'Aquest recurs està bloquejat.',
        '429' => 'Massa peticions. Espera una mica i torna-ho a provar.',
        '500' => 'S\'ha produït un error al servidor.',
        '503' => 'L\'aplicació està en manteniment. Torna-ho a provar més tard.',
    ],

    'users' => [
        'last_admin' => 'Ha de quedar com a mínim un administrador actiu.',
        'cannot_delete_self' => 'No pots donar-te de baixa a tu mateix.',
        'cannot_deactivate_self' => 'No pots desactivar el teu propi usuari.',
    ],

    'validation' => [
        'quarter_hour' => 'L\'hora ha de ser en punt, i quart, i mitja o menys quart.',
        'time_step' => 'L\'hora ha de ser múltiple de :step minuts.',
        'operating_hours' => 'L\'hora del partit ha d\'estar entre les :from i les :to.',
        'result_cancelled' => 'Un partit cancel·lat no pot tenir resultat.',
        'call_after_kickoff' => 'La convocatòria no pot ser posterior a l\'hora del partit.',
        'f11_full_field' => 'Un partit de futbol 11 necessita el camp complet.',
        'placement_incomplete' => 'Per col·locar un partit a casa cal indicar la data, l\'hora i el camp.',
        'area_only_home' => 'Només els partits a casa ocupen un espai del camp.',
        'last_kickoff_before_open' => 'L\'última hora d\'inici no pot ser anterior a la primera.',
        'not_after_before_not_before' => 'L\'hora «no després de» no pot ser anterior a l\'hora «no abans de».',
        'rule_single_scope' => 'Indica només un àmbit: un equip, una categoria o una modalitat.',
        'rule_scope_required' => 'Indica a quin equip, categoria o modalitat s\'aplica la regla.',
        'rule_content_required' => 'Indica una hora preferent o una finestra horària (no abans de / no després de).',
        'rule_weekday_required' => 'Cal indicar el dia de l\'hora preferent.',
        'rule_area_requires_time' => 'El camp preferent només s\'aplica amb una hora preferent.',
        'team_already_has_match' => 'Aquest equip ja té un partit en aquesta jornada.',
        'matchday_outside_season' => 'La jornada ha d\'estar dins de la temporada.',
        'logo_unreadable' => 'No s\'ha pogut llegir la imatge. Puja un fitxer PNG, JPEG o WebP vàlid.',
    ],

    'categories' => [
        'has_teams' => 'No es pot esborrar una categoria que té equips. Desactiva-la o mou-ne els equips.',
    ],

    'seasons' => [
        'has_matchdays' => 'No es pot esborrar una temporada que té jornades.',
    ],

    'matchdays' => [
        'has_matches' => 'No es pot esborrar una jornada que té partits.',
    ],

    'mail_test_subject' => 'Correu de prova',
    'mail_test_body' => 'Si has rebut aquest missatge, la configuració del correu funciona correctament.',
    'mail_test_failed' => 'No s\'ha pogut enviar el correu de prova: :error',
];
