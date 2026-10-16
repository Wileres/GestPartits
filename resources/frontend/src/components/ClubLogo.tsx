import { cn } from '@/lib/utils';

/**
 * Escut genèric blau i blanc (el logo real del club es configurarà des d'Aparença).
 */
export function ClubLogo({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 48 56" className={cn('size-10', className)} role="img" aria-label="Joventut Sant Pere Màrtir">
            <path d="M24 2 4 9v17c0 13.5 8.6 23.7 20 28 11.4-4.3 20-14.5 20-28V9L24 2Z" fill="#0B4EA2" stroke="#ffffff" strokeWidth="2.5" />
            <path d="M24 9 11 13.5V26c0 9 5.3 16.2 13 19.6V9Z" fill="#ffffff" />
            <text x="24" y="33" textAnchor="middle" fontFamily="Inter Variable, sans-serif" fontSize="11" fontWeight="800" fill="#082F66" stroke="#ffffff" strokeWidth="0.4" paintOrder="stroke">
                SPM
            </text>
        </svg>
    );
}
