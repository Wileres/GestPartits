import { ApiError } from '@/api/client';
import { t } from '@/i18n/ca';

/**
 * Missatge per a un toast a partir d'un error de l'API (primer error de validació o missatge general).
 */
export function errorMessage(error: unknown): string {
    if (!(error instanceof ApiError)) return t('common.error');
    if (error.status === 0) return t('common.networkError');
    if (error.status === 409) return t('common.conflict');

    const first = Object.values(error.errors)[0]?.[0];

    return first ?? (error.message !== '' ? error.message : t('common.error'));
}

/** Converteix un camp de text buit en null. */
export function emptyToNull(value: string | null | undefined): string | null {
    return value === undefined || value === null || value.trim() === '' ? null : value.trim();
}

/** Converteix un camp numèric de formulari en número o null. */
export function numberOrNull(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}
