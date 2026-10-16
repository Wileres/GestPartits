import { Bus, CircleCheck, CircleDashed, CircleSlash, Clock, House, Lock, PauseCircle, Sparkles } from 'lucide-react';
import type { ComponentType } from 'react';
import type { HomeAway, MatchStatus } from '@/api/types';
import { Badge } from '@/components/ui/badge';
import { t } from '@/i18n/ca';
import { cn } from '@/lib/utils';

const statusStyle: Record<MatchStatus, { icon: ComponentType<{ className?: string }>; className: string }> = {
    pending: { icon: Clock, className: 'border-status-warning/40 bg-status-warning-soft text-status-warning' },
    proposed: { icon: Sparkles, className: 'border-primary/30 bg-secondary text-primary' },
    confirmed: { icon: CircleCheck, className: 'border-status-locked/40 bg-status-locked-soft text-status-locked' },
    postponed: { icon: PauseCircle, className: 'border-muted-foreground/30 bg-muted text-muted-foreground' },
    cancelled: { icon: CircleSlash, className: 'border-muted-foreground/30 bg-muted text-muted-foreground line-through' },
    rest: { icon: CircleDashed, className: 'border-muted-foreground/30 bg-muted text-muted-foreground' },
};

/** Estat del partit: sempre amb icona i text, mai només color. */
export function StatusBadge({ status }: { status: MatchStatus }) {
    const { icon: Icon, className } = statusStyle[status];

    return (
        <Badge variant="outline" className={cn('gap-1', className)}>
            <Icon className="size-3" aria-hidden />
            {t(`labels.status.${status}`)}
        </Badge>
    );
}

export function HomeAwayBadge({ value }: { value: HomeAway }) {
    const Icon = value === 'home' ? House : Bus;

    return (
        <Badge variant="outline" className={cn('gap-1', value === 'home' ? 'border-primary/30 bg-secondary text-primary' : 'bg-card')}>
            <Icon className="size-3" aria-hidden />
            {t(`labels.homeAwayShort.${value}`)}
        </Badge>
    );
}

export function LockedBadge() {
    return (
        <Badge variant="outline" className="gap-1 border-status-locked/40 bg-status-locked-soft text-status-locked">
            <Lock className="size-3" aria-hidden />
            {t('matches.locked')}
        </Badge>
    );
}
