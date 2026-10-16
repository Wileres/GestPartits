<?php

namespace App\Domain\Messaging;

use InvalidArgumentException;

/**
 * Plantilles de missatge amb variables {{nom}}. Només se substitueixen les variables de la llista
 * tancada (no s'avalua cap codi). Una línia que conté una variable sense valor s'elimina, perquè els
 * camps opcionals (p. ex. la convocatòria o l'adreça) no deixin línies a mitges.
 */
final class TemplateRenderer
{
    /** Variables d'un missatge de partit. */
    public const MATCH_VARIABLES = [
        'equip', 'rival', 'data', 'dia', 'hora_convocatoria', 'hora_partit', 'installacio', 'adreca',
        'municipi', 'mapa', 'observacions', 'informacio', 'jornada', 'camp',
    ];

    /** Variables del resum de jornada (canal del club). */
    public const SUMMARY_VARIABLES = ['jornada', 'partits_dissabte', 'partits_diumenge', 'club'];

    /** Variables de cada línia del resum. */
    public const LINE_VARIABLES = ['icona', 'hora_partit', 'hora_convocatoria', 'equip', 'rival', 'municipi', 'installacio', 'camp'];

    private const PATTERN = '/\{\{\s*([a-z_]+)\s*\}\}/';

    /**
     * @return list<string> Variables que apareixen a la plantilla.
     */
    public function variables(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string> Variables desconegudes (buit si la plantilla és vàlida).
     */
    public function unknownVariables(string $template, array $allowed): array
    {
        return array_values(array_diff($this->variables($template), $allowed));
    }

    /**
     * @param  array<string, string|null>  $values
     * @param  list<string>  $allowed
     */
    public function render(string $template, array $values, array $allowed): string
    {
        $unknown = $this->unknownVariables($template, $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Variables desconegudes: '.implode(', ', $unknown));
        }

        $lines = [];
        // Només salts de línia reals: \R sense el modificador «u» partiria emojis que contenen el byte 0x85.
        foreach (preg_split('/\r\n|\n|\r/u', $template) ?: [] as $line) {
            $missing = false;
            $rendered = preg_replace_callback(self::PATTERN, function (array $m) use ($values, &$missing): string {
                $value = $values[$m[1]] ?? null;
                if ($value === null || trim($value) === '') {
                    $missing = true;

                    return '';
                }

                return $value;
            }, $line);

            if (! $missing) {
                $lines[] = rtrim((string) $rendered);
            }
        }

        // Sense línies en blanc repetides ni al principi o al final.
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim((string) $text);
    }
}
