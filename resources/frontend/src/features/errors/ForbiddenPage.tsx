import { ShieldAlert } from 'lucide-react';
import { Link } from 'react-router';
import { t } from '@/i18n/ca';

export function ForbiddenPage() {
    return (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
            <ShieldAlert className="size-10 text-status-critical" aria-hidden />
            <h1 className="text-xl font-semibold">{t('common.forbidden')}</h1>
            <Link to="/" className="text-sm text-primary hover:underline">
                {t('common.backHome')}
            </Link>
        </div>
    );
}
