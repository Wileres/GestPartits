import { ca } from '@/i18n/ca';

/**
 * Dates locals sense zona horària (les dates dels partits no es converteixen).
 * L'API fa servir «AAAA-MM-DD»; la interfície sempre mostra «DD/MM/AAAA».
 */
function parse(date: string): Date {
    const [y, m, d] = date.split('-').map(Number);

    return new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1));
}

export function isoWeekday(date: string): number {
    const day = parse(date).getUTCDay();

    return day === 0 ? 7 : day;
}

export function weekdayName(isoDay: number): string {
    return ca.weekdays[isoDay - 1] ?? '';
}

export function addDays(date: string, days: number): string {
    const value = parse(date);
    value.setUTCDate(value.getUTCDate() + days);

    return value.toISOString().slice(0, 10);
}

/** «2026-10-17» → «17/10/2026» */
export function formatDate(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-');

    return `${d ?? ''}/${m ?? ''}/${y ?? ''}`;
}

/** «2026-10-17» → «dissabte 17/10/2026» */
export function formatWeekdayDate(iso: string): string {
    return `${weekdayName(isoWeekday(iso))} ${formatDate(iso)}`;
}

/** «2026-10-17T09:00» → «17/10/2026 09:00» */
export function formatLocalDateTime(value: string): string {
    const [date, time] = value.split('T');

    return `${formatDate(date ?? '')} ${(time ?? '').slice(0, 5)}`;
}

/** «17/10/2026» → «2026-10-17»; null si no és una data real. */
export function parseDisplayDate(value: string): string | null {
    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim());
    if (match === null) return null;

    const iso = `${match[3]}-${match[2]}-${match[1]}`;
    const date = parse(iso);

    return date.getUTCFullYear() === Number(match[3]) && date.getUTCMonth() + 1 === Number(match[2]) && date.getUTCDate() === Number(match[1]) ? iso : null;
}

/** «2026-10-17» → «17/10/2026» (buit si no hi ha data). */
export function toDisplayDate(iso: string | null | undefined): string {
    return iso === null || iso === undefined || iso === '' ? '' : formatDate(iso);
}

/**
 * Formata mentre s'escriu: «17102026» → «17/10/2026».
 */
export function maskDisplayDate(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 8);
    const parts = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter((p) => p !== '');

    return parts.join('/');
}
