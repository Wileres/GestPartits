import { Loader2 } from 'lucide-react';
import { t } from '@/i18n/ca';

export function FullPageSpinner() {
    return (
        <div className="flex min-h-svh items-center justify-center" role="status">
            <Loader2 className="size-6 animate-spin text-primary" aria-hidden />
            <span className="sr-only">{t('app.loading')}</span>
        </div>
    );
}
