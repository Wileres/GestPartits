import { z } from 'zod';
import { t } from '@/i18n/ca';

/**
 * Validació al navegador de la contrasenya nova (el servidor aplica la política completa).
 */
export const newPasswordFields = {
    password: z.string().min(12, t('common.minLength', { min: 12 })),
    password_confirmation: z.string().min(1, t('common.required')),
};

export function passwordsMatch(values: { password: string; password_confirmation: string }): boolean {
    return values.password === values.password_confirmation;
}

export const passwordsMatchIssue = { message: t('common.passwordsDontMatch'), path: ['password_confirmation'] };
