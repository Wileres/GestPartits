import { describe, expect, it } from 'vitest';
import { addDays, formatDate, formatLocalDateTime, formatWeekdayDate, isoWeekday, maskDisplayDate, parseDisplayDate, toDisplayDate, weekdayName } from '@/lib/dates';
import { formatDateTime } from '@/i18n/ca';
import { describeRule } from '@/lib/rules';
import { fromMinutes, isOnGrid, isWithin, toMinutes } from '@/lib/time';

describe('hores', () => {
    it('converteix hores i minuts', () => {
        expect(toMinutes('11:15')).toBe(675);
        expect(fromMinutes(675)).toBe('11:15');
    });

    it('comprova la graella de 15 minuts i l’horari operatiu', () => {
        expect(isOnGrid('10:45', 15)).toBe(true);
        expect(isOnGrid('10:10', 15)).toBe(false);
        expect(isWithin('21:00', '09:00', '21:00')).toBe(true);
        expect(isWithin('21:15', '09:00', '21:00')).toBe(false);
    });
});

describe('dates', () => {
    it('calcula el dia de la setmana sense dependre de la zona horària', () => {
        expect(isoWeekday('2026-10-17')).toBe(6);
        expect(isoWeekday('2026-10-18')).toBe(7);
        expect(weekdayName(6)).toBe('dissabte');
        expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
    });

    it('mostra les dates en format DD/MM/AAAA', () => {
        expect(formatDate('2026-10-17')).toBe('17/10/2026');
        expect(formatDate('2026-01-05')).toBe('05/01/2026');
        expect(formatWeekdayDate('2026-10-17')).toBe('dissabte 17/10/2026');
        expect(formatLocalDateTime('2026-10-17T09:00')).toBe('17/10/2026 09:00');
        // Instant UTC mostrat en hora del club.
        expect(formatDateTime('2026-10-15T23:15:00Z')).toBe('16/10/2026 01:15');
    });

    it('llegeix i formata les dates que s’escriuen', () => {
        expect(maskDisplayDate('17102026')).toBe('17/10/2026');
        expect(maskDisplayDate('1710')).toBe('17/10');
        expect(parseDisplayDate('17/10/2026')).toBe('2026-10-17');
        expect(parseDisplayDate('31/02/2026')).toBeNull();
        expect(parseDisplayDate('2026-10-17')).toBeNull();
        expect(toDisplayDate(null)).toBe('');
    });
});

describe('regles', () => {
    it('resumeix una regla en català', () => {
        const base = {
            id: 1, team_id: 1, category_id: null, game_format: null, scope_label: 'Benjamí A', priority: 80, strength: 'recommended' as const,
            valid_from: null, valid_to: null, is_active: true, notes: null, not_before: null, not_after: null,
        };

        expect(describeRule({ ...base, weekday: 7, preferred_time: '12:30', preferred_field_area_id: 1 }, [{ id: 1, code: 'camp_1', name: 'Camp 1', kind: 'half' }])).toBe(
            'diumenge 12:30 · Camp 1',
        );
        expect(describeRule({ ...base, weekday: null, preferred_time: null, preferred_field_area_id: null, not_before: '10:00' })).toBe('no abans de 10:00');
    });
});
