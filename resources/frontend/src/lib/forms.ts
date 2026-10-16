import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { ApiError } from '@/api/client';
import { t } from '@/i18n/ca';

/**
 * Trasllada els errors de validació del servidor (422) als camps del formulari.
 * Retorna el missatge general per mostrar quan l'error no és d'un camp concret.
 */
export function applyServerErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>, fields: readonly Path<T>[]): string | null {
    if (!(error instanceof ApiError)) {
        return t('common.error');
    }

    if (error.status === 0) {
        return t('common.networkError');
    }

    let unassigned: string | null = null;

    for (const [field, messages] of Object.entries(error.errors)) {
        const message = messages[0];
        if (message === undefined) continue;

        if ((fields as readonly string[]).includes(field)) {
            setError(field as Path<T>, { type: 'server', message });
        } else {
            unassigned ??= message;
        }
    }

    if (Object.keys(error.errors).length === 0) {
        return error.message !== '' ? error.message : t('common.error');
    }

    return unassigned;
}
