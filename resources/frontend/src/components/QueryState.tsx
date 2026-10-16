import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { t } from '@/i18n/ca';

export function LoadingState() {
    return (
        <div className="flex justify-center py-10" role="status">
            <Loader2 className="size-6 animate-spin text-primary" aria-hidden />
            <span className="sr-only">{t('app.loading')}</span>
        </div>
    );
}

export function ErrorState({ message }: { message?: string }) {
    return (
        <Alert variant="destructive" role="alert">
            <AlertDescription>{message ?? t('common.error')}</AlertDescription>
        </Alert>
    );
}

export function EmptyState({ icon, title, children }: { icon?: ReactNode; title: string; children?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-card px-4 py-10 text-center">
            {icon}
            <p className="font-medium">{title}</p>
            {children}
        </div>
    );
}
