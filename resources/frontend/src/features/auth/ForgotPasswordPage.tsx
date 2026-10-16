import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router';
import { z } from 'zod';
import { authApi } from '@/api/auth';
import { FormField } from '@/components/FormField';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { applyServerErrors } from '@/lib/forms';

const schema = z.object({
    email: z.email(t('common.invalidEmail')),
});

type FormValues = z.infer<typeof schema>;

export function ForgotPasswordPage() {
    const [sentMessage, setSentMessage] = useState<string | null>(null);
    const [formError, setFormError] = useState<string | null>(null);
    const forgot = useMutation({ mutationFn: (email: string) => authApi.forgotPassword(email) });

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { email: '' } });

    const onSubmit = handleSubmit(({ email }) => {
        setFormError(null);
        forgot.mutate(email, {
            onSuccess: (response) => setSentMessage(response.message),
            onError: (error) => setFormError(applyServerErrors(error, setError, ['email'] as const)),
        });
    });

    return (
        <AuthLayout title={t('auth.forgotTitle')} description={sentMessage === null ? t('auth.forgotHelp') : undefined}>
            {sentMessage !== null ? (
                <div className="grid gap-4">
                    <Alert role="status">
                        <AlertDescription>{sentMessage}</AlertDescription>
                    </Alert>
                    <Link to="/inicia-sessio" className="text-center text-sm text-primary hover:underline">
                        {t('auth.backToLogin')}
                    </Link>
                </div>
            ) : (
                <form onSubmit={onSubmit} noValidate className="grid gap-4">
                    {formError !== null && (
                        <Alert variant="destructive" role="alert">
                            <AlertDescription>{formError}</AlertDescription>
                        </Alert>
                    )}
                    <FormField id="email" label={t('auth.email')} error={errors.email?.message}>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            autoFocus
                            aria-invalid={errors.email !== undefined}
                            aria-describedby={describedBy('email', errors.email?.message)}
                            {...register('email')}
                        />
                    </FormField>
                    <Button type="submit" disabled={forgot.isPending}>
                        {forgot.isPending ? t('auth.sending') : t('auth.sendLink')}
                    </Button>
                    <Link to="/inicia-sessio" className="text-center text-sm text-primary hover:underline">
                        {t('auth.backToLogin')}
                    </Link>
                </form>
            )}
        </AuthLayout>
    );
}
