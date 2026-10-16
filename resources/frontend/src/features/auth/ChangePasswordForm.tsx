import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { authApi, type ChangePasswordInput } from '@/api/auth';
import type { Me } from '@/api/types';
import { FormField } from '@/components/FormField';
import { PasswordInput } from '@/components/PasswordInput';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { meQueryKey } from '@/hooks/useAuth';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { applyServerErrors } from '@/lib/forms';
import { newPasswordFields, passwordsMatch, passwordsMatchIssue } from './passwordSchema';

const schema = z
    .object({
        current_password: z.string().min(1, t('common.required')),
        ...newPasswordFields,
    })
    .refine(passwordsMatch, passwordsMatchIssue);

type FormValues = z.infer<typeof schema>;

const fields = ['current_password', 'password', 'password_confirmation'] as const;

export function ChangePasswordForm({ onChanged }: { onChanged: (me: Me) => void }) {
    const queryClient = useQueryClient();
    const [formError, setFormError] = useState<string | null>(null);
    const change = useMutation({
        mutationFn: (input: ChangePasswordInput) => authApi.changePassword(input),
        onSuccess: (me) => queryClient.setQueryData(meQueryKey, me),
    });

    const {
        register,
        handleSubmit,
        setError,
        reset,
        formState: { errors },
    } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { current_password: '', password: '', password_confirmation: '' } });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        change.mutate(values, {
            onSuccess: (me) => {
                reset();
                onChanged(me);
            },
            onError: (error) => setFormError(applyServerErrors(error, setError, fields)),
        });
    });

    return (
        <form onSubmit={onSubmit} noValidate className="grid gap-4">
            {formError !== null && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{formError}</AlertDescription>
                </Alert>
            )}
            <FormField id="current_password" label={t('auth.currentPassword')} error={errors.current_password?.message}>
                <PasswordInput
                    id="current_password"
                    autoComplete="current-password"
                    aria-invalid={errors.current_password !== undefined}
                    aria-describedby={describedBy('current_password', errors.current_password?.message)}
                    {...register('current_password')}
                />
            </FormField>
            <FormField id="password" label={t('auth.newPassword')} error={errors.password?.message} help={t('auth.passwordPolicy')}>
                <PasswordInput
                    id="password"
                    autoComplete="new-password"
                    aria-invalid={errors.password !== undefined}
                    aria-describedby={describedBy('password', errors.password?.message, t('auth.passwordPolicy'))}
                    {...register('password')}
                />
            </FormField>
            <FormField id="password_confirmation" label={t('auth.confirmPassword')} error={errors.password_confirmation?.message}>
                <PasswordInput
                    id="password_confirmation"
                    autoComplete="new-password"
                    aria-invalid={errors.password_confirmation !== undefined}
                    aria-describedby={describedBy('password_confirmation', errors.password_confirmation?.message)}
                    {...register('password_confirmation')}
                />
            </FormField>
            <Button type="submit" disabled={change.isPending}>
                {change.isPending ? t('auth.saving') : t('auth.save')}
            </Button>
        </form>
    );
}
