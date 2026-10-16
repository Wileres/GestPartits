import type { Match } from '@/api/types';
import { minutes } from '@/domain/scheduling/detector';
import type { Area, ScheduleInput } from '@/domain/scheduling/types';
import { t } from '@/i18n/ca';
import { fromMinutes, toMinutes } from '@/lib/time';

/** Píxels per cada quart d'hora del calendari. */
export const ROW = 18;

export interface TimeRange {
    start: number;
    end: number;
}

/**
 * Interval visible: de la primera hora d'inici permesa fins a dues hores després de l'última
 * (o més, si algun partit acaba més tard). Els dos dies fan servir el mateix per alinear-se.
 */
export function visibleRange(input: ScheduleInput, matches: Match[]): TimeRange {
    const start = minutes(input.operating.first);
    let end = minutes(input.operating.last) + 120;

    for (const match of matches) {
        if (match.kickoff_time !== null) {
            end = Math.max(end, minutes(match.kickoff_time) + match.duration_min + match.post_min);
        }
    }

    return { start, end: Math.ceil(end / 60) * 60 };
}

export const offset = (minute: number, range: TimeRange) => ((minute - range.start) / 15) * ROW;
export const span = (durationMin: number) => (durationMin / 15) * ROW;

export const HALF_COLUMNS: Exclude<Area, 'full'>[] = ['camp_1', 'camp_2'];

/** Columna d'una casella del calendari: «slot:2026-10-17:camp_1:660». */
export function cellId(date: string, column: Exclude<Area, 'full'>, minute: number): string {
    return `slot:${date}:${column}:${minute}`;
}

export function parseCellId(id: string): { date: string; column: Exclude<Area, 'full'>; minute: number } | null {
    const match = /^slot:(\d{4}-\d{2}-\d{2}):(camp_1|camp_2):(\d+)$/.exec(id);

    return match === null ? null : { date: match[1] as string, column: match[2] as Exclude<Area, 'full'>, minute: Number(match[3]) };
}

export const TRAY_ID = 'safata-sense-programar';

export function isHomeActive(match: Match): boolean {
    return match.home_away === 'home' && ['pending', 'proposed', 'confirmed'].includes(match.status);
}

/** «11:15–12:15» (inici i final del partit, sense el marge posterior). */
export function matchTimes(match: Match): string {
    if (match.kickoff_time === null) return t('schedule.noTime');

    return `${match.kickoff_time}–${fromMinutes(toMinutes(match.kickoff_time) + match.duration_min)}`;
}
