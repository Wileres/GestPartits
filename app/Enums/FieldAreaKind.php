<?php

namespace App\Enums;

enum FieldAreaKind: string
{
    /** Mig camp: hi cap un partit de futbol 7. */
    case Half = 'half';
    /** Camp complet: ocupa totes les meitats de la instal·lació. */
    case Full = 'full';
}
