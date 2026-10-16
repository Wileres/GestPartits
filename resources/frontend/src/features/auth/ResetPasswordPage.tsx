import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { toast } from 'sonner';
import { z } from 'zod';
import { authApi, type ResetPasswordInput } from '@/api/auth';
import { FormField } from '@/components/FormField';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { PasswordInput } from '@/components/PasswordInput';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { applyServerErrors } from '@/lib/forms';
import { newPasswordFields, passwordsMatch, passwordsMatchIssue } from './passwordSchema';

const schema = z.object(newPasswordFields).refine(passwordsMatch, passwordsMatchIssue);

type FormValues = z.infer<typeof schema>;

export function ResetPasswordPage() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const token = params.get('token') ?? '';
    const email = params.get('email') ?? '';
    const [formError, setFormError] = useState<string | null>(null);
    const reset = useMutation({ mutationFn: (input: ResetPasswordInput) => authApi.resetPassword(input) });

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { password: '', password_confirmation: '' } });

    if (token === '' || email === '') {
        return (
            <AuthLayout title={t('auth.resetTitle')}>
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{t('auth.resetInvalidLink')}</AlertDescription>
                </Alert>
                <Link to="/recupera-contrasenya" className="mt-4 block text-center text-sm text-primary hover:underline">
                    {t('auth.forgotTitle')}
                </Link>
            </AuthLayout>
        );
    }

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        reset.mutate(
            { token, email, ...values },
            {
                onSuccess: (response) => {
                    toast.success(response.message);
                    void navigate('/inicia-sessio', { replace: true });
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, ['password', 'password_confirmation'] as const)),
            },
        );
    });

    return (
        <AuthLayout title={t('auth.resetTitle')} description={email}>
            <form onSubmit={onSubmit} noValidate className="grid gap-4">
                {formError !== null && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}
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
                <Button type="submit" disabled={reset.isPending}>
                    {reset.isPending ? t('auth.saving') : t('auth.save')}
                </Button>
            </form>
        </AuthLayout>
    );
}
