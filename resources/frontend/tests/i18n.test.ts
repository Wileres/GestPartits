import { describe, expect, it } from 'vitest';
import { ca, t, type TranslationKey } from '@/i18n/ca';

function leaves(node: unknown, prefix = ''): [string, unknown][] {
    if (typeof node === 'string' || Array.isArray(node)) return [[prefix, node]];

    return Object.entries(node as Record<string, unknown>).flatMap(([key, value]) => leaves(value, prefix === '' ? key : `${prefix}.${key}`));
}

describe('textos en català', () => {
    it('substitueix els paràmetres', () => {
        expect(t('schedule.confirmedBy', { name: 'Gestor', date: '17/10/2026 10:00' })).toBe('Confirmada per Gestor el 17/10/2026 10:00');
        expect(t('common.minLength', { min: 12 })).toBe('Ha de tenir com a mínim 12 caràcters.');
    });

    it('falla si la clau no existeix', () => {
        expect(() => t('no.existeix' as TranslationKey)).toThrow('Falta la traducció');
    });

    it('no té cap text buit', () => {
        for (const [key, value] of leaves(ca)) {
            expect(value, key).not.toHaveLength(0);
        }
    });

    it('no conté textos habituals en anglès', () => {
        // La paraula no pot continuar amb una lletra ni amb el punt volat (Cancel·la és català).
        // «Error» no hi és: també és una paraula catalana.
        const english = /\b(Save|Cancel|Log ?in|Log ?out|Password|Loading|Delete|Edit|Users?|Submit|Settings|Send|Sent|Failed)(?![\p{L}·])/u;

        for (const [key, value] of leaves(ca)) {
            for (const text of Array.isArray(value) ? value : [value]) {
                expect(String(text), key).not.toMatch(english);
            }
        }
    });

    it('no conté paraules castellanes habituals', () => {
        const spanish = /\b(importantes|usuarios?|contraseñas?|guardar|borrar|eliminar|también|partidos?|equipos?|fecha|campo|semana|después|cuando|ahora|está|están|puede|pueden|ningún|configuración)\b/iu;

        for (const [key, value] of leaves(ca)) {
            for (const text of Array.isArray(value) ? value : [value]) {
                expect(String(text), key).not.toMatch(spanish);
            }
        }
    });

    it('té els dies de la setmana en català', () => {
        expect(ca.weekdays).toEqual(['dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte', 'diumenge']);
    });
});
