import { usePublicBranding } from '@/hooks/usePublicBranding';
import { t } from '@/i18n/ca';
import { cn } from '@/lib/utils';

/**
 * Escut del club: el que s'ha pujat a Configuració → Aparença (és el mateix per a tots els equips) o,
 * mentre no n'hi ha cap, un escut genèric blau i blanc.
 */
export function ClubLogo({ className }: { className?: string }) {
    const { data, isPending } = usePublicBranding();

    if (isPending) {
        return <span className={cn('inline-block size-10', className)} aria-hidden />;
    }

    if (data?.logo_url != null) {
        return <img src={data.logo_url} alt={data.club_name} className={cn('size-10 object-contain', className)} />;
    }

    return (
        <svg viewBox="0 0 48 56" className={cn('size-10', className)} role="img" aria-label={data?.club_name ?? t('app.club')}>
            <path d="M24 2 4 9v17c0 13.5 8.6 23.7 20 28 11.4-4.3 20-14.5 20-28V9L24 2Z" fill="#0B4EA2" stroke="#ffffff" strokeWidth="2.5" />
            <path d="M24 9 11 13.5V26c0 9 5.3 16.2 13 19.6V9Z" fill="#ffffff" />
            <text x="24" y="33" textAnchor="middle" fontFamily="Inter Variable, sans-serif" fontSize="11" fontWeight="800" fill="#082F66" stroke="#ffffff" strokeWidth="0.4" paintOrder="stroke">
                SPM
            </text>
        </svg>
    );
}
