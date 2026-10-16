import { z } from 'zod';
import { t } from '@/i18n/ca';
import { parseDisplayDate } from './dates';

/** Data obligatòria en format DD/MM/AAAA. */
export const requiredDisplayDate = z
    .string()
    .min(1, t('common.required'))
    .refine((v) => v === '' || parseDisplayDate(v) !== null, t('common.invalidDate'));

/** Data opcional en format DD/MM/AAAA. */
export const optionalDisplayDate = z.string().refine((v) => v.trim() === '' || parseDisplayDate(v) !== null, t('common.invalidDate'));

/** «17/10/2026» → «2026-10-17» (o null si és buida). */
export function displayToIso(value: string): string | null {
    return value.trim() === '' ? null : parseDisplayDate(value);
}

/** Cert si les dues dates són vàlides i «to» és igual o posterior a «from». */
export function isSameOrAfter(from: string, to: string): boolean {
    const a = parseDisplayDate(from);
    const b = parseDisplayDate(to);

    return a === null || b === null || b >= a;
}
