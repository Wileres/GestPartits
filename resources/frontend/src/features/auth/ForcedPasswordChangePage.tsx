import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { Button } from '@/components/ui/button';
import { useLogout } from '@/hooks/useAuth';
import { t } from '@/i18n/ca';
import { ChangePasswordForm } from './ChangePasswordForm';

/**
 * Canvi obligatori de la contrasenya inicial o temporal.
 */
export function ForcedPasswordChangePage() {
    const navigate = useNavigate();
    const logout = useLogout();

    return (
        <AuthLayout title={t('auth.changeTitle')} description={t('auth.changeForcedHelp')}>
            <ChangePasswordForm
                onChanged={() => {
                    toast.success(t('auth.changed'));
                    void navigate('/', { replace: true });
                }}
            />
            <Button
                variant="ghost"
                className="mt-2 w-full"
                onClick={() => logout.mutate(undefined, { onSettled: () => void navigate('/inicia-sessio', { replace: true }) })}
            >
                {t('nav.logout')}
            </Button>
        </AuthLayout>
    );
}
