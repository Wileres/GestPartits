import { SearchX } from 'lucide-react';
import { Link } from 'react-router';
import { t } from '@/i18n/ca';

export function NotFoundPage() {
    return (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
            <SearchX className="size-10 text-muted-foreground" aria-hidden />
            <h1 className="text-xl font-semibold">{t('common.notFound')}</h1>
            <Link to="/" className="text-sm text-primary hover:underline">
                {t('common.backHome')}
            </Link>
        </div>
    );
}
