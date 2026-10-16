import { toast } from 'sonner';
import type { Me } from '@/api/types';
import { PageHeader } from '@/components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/i18n/ca';
import { ChangePasswordForm } from './ChangePasswordForm';

export function ProfilePage({ me }: { me: Me }) {
    return (
        <>
            <PageHeader title={t('nav.profile')} description={`${me.name} · ${me.role_label}`} />
            <Card className="max-w-lg">
                <CardHeader>
                    <CardTitle>{t('auth.changeTitle')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ChangePasswordForm onChanged={() => toast.success(t('auth.changed'))} />
                </CardContent>
            </Card>
        </>
    );
}
