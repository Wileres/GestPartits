import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useLocation, useNavigate } from 'react-router';
import { z } from 'zod';
import { FormField } from '@/components/FormField';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { PasswordInput } from '@/components/PasswordInput';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLogin } from '@/hooks/useAuth';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { applyServerErrors } from '@/lib/forms';

const schema = z.object({
    username: z.string().trim().min(1, t('common.required')),
    password: z.string().min(1, t('common.required')),
    remember: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

export function LoginPage() {
    const login = useLogin();
    const navigate = useNavigate();
    const location = useLocation();
    const [formError, setFormError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { username: '', password: '', remember: false } });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        login.mutate(values, {
            onSuccess: (me) => {
                const from = (location.state as { from?: string } | null)?.from;
                void navigate(me.must_change_password ? '/canvia-contrasenya' : (from ?? '/'), { replace: true });
            },
            // «Usuari o contrasenya incorrectes» i el bloqueig per intents es mostren a dalt del formulari.
            onError: (error) => setFormError(applyServerErrors(error, setError, [])),
        });
    });

    return (
        <AuthLayout title={t('auth.loginTitle')} description={t('auth.loginSubtitle')}>
            <form onSubmit={onSubmit} noValidate className="grid gap-4">
                {formError !== null && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}
                <FormField id="username" label={t('auth.username')} error={errors.username?.message}>
                    <Input
                        id="username"
                        autoComplete="username"
                        autoFocus
                        aria-invalid={errors.username !== undefined}
                        aria-describedby={describedBy('username', errors.username?.message)}
                        {...register('username')}
                    />
                </FormField>
                <FormField id="password" label={t('auth.password')} error={errors.password?.message}>
                    <PasswordInput
                        id="password"
                        autoComplete="current-password"
                        aria-invalid={errors.password !== undefined}
                        aria-describedby={describedBy('password', errors.password?.message)}
                        {...register('password')}
                    />
                </FormField>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" className="size-4 accent-primary" {...register('remember')} />
                    {t('auth.remember')}
                </label>
                <Button type="submit" disabled={login.isPending} className="w-full">
                    {login.isPending ? t('auth.loggingIn') : t('auth.login')}
                </Button>
                <Link to="/recupera-contrasenya" className="text-center text-sm text-primary underline-offset-4 hover:underline">
                    {t('auth.forgotLink')}
                </Link>
            </form>
        </AuthLayout>
    );
}

