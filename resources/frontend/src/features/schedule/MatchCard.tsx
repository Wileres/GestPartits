import { useDraggable } from '@dnd-kit/core';
import { AlertTriangle, Lock, LockOpen, Move, OctagonAlert } from 'lucide-react';
import type { CSSProperties } from 'react';
import type { Match } from '@/api/types';
import type { Severity } from '@/domain/scheduling/types';
import { t } from '@/i18n/ca';
import { cn } from '@/lib/utils';
import { matchTimes } from './layout';

export type CardState = Severity | 'valid';

interface MatchCardProps {
    match: Match;
    state: CardState;
    conflictCount: number;
    compact?: boolean;
    style?: CSSProperties;
    /** Proporció de la targeta que correspon al partit (la resta és el marge posterior). */
    playRatio?: number;
    onOpen: () => void;
    onToggleLock: () => void;
    onMoveTo: () => void;
    dragging?: boolean;
}

const stateStyles: Record<CardState, string> = {
    critical: 'border-status-critical bg-status-critical-soft',
    warning: 'border-status-warning bg-status-warning-soft',
    valid: 'border-status-valid/60 bg-card',
};

/**
 * Targeta d'un partit al calendari: equip, rival, horari, estat, bloqueig i conflictes.
 */
export function MatchCard({ match, state, conflictCount, compact = false, style, playRatio = 1, onOpen, onToggleLock, onMoveTo, dragging = false }: MatchCardProps) {
    const fixed = match.is_locked || match.status === 'confirmed';
    const StateIcon = state === 'critical' ? OctagonAlert : AlertTriangle;

    return (
        <div
            style={style}
            className={cn(
                'group relative flex overflow-hidden rounded-md border-2 text-left shadow-sm',
                stateStyles[state],
                fixed && 'ring-2 ring-status-locked/50',
                dragging && 'opacity-40',
            )}
        >
            <span className="w-1.5 shrink-0" style={{ backgroundColor: match.team.color ?? 'var(--primary)' }} aria-hidden />
            <div className="relative min-w-0 flex-1">
                {/* Marge posterior: part ratllada sota el partit. */}
                {playRatio < 1 && (
                    <span
                        className="absolute inset-x-0 bottom-0 bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,rgba(15,31,56,0.06)_4px,rgba(15,31,56,0.06)_8px)]"
                        style={{ height: `${(1 - playRatio) * 100}%` }}
                        aria-hidden
                    />
                )}
                <button type="button" onClick={onOpen} className="relative block w-full px-1.5 py-1 text-left focus-visible:outline-2 focus-visible:outline-ring" aria-label={t('schedule.editMatch', { team: match.team.name })}>
                    <span className="block truncate text-xs leading-tight font-semibold">{match.team.name}</span>
                    {!compact && <span className="block truncate text-[11px] leading-tight text-muted-foreground">{match.opponent ?? '—'}</span>}
                    <span className="block truncate text-[11px] leading-tight tabular-nums">{matchTimes(match)}</span>
                </button>
                <div className="absolute top-0.5 right-0.5 flex items-center gap-0.5">
                    {conflictCount > 0 && state !== 'valid' && (
                        <span className={cn('rounded p-0.5', state === 'critical' ? 'text-status-critical' : 'text-status-warning')} title={String(conflictCount)}>
                            <StateIcon className="size-3.5" aria-label={state === 'critical' ? t('schedule.legendCritical') : t('schedule.legendWarning')} />
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={onToggleLock}
                        className={cn('rounded p-0.5 hover:bg-black/5', match.is_locked ? 'text-status-locked' : 'text-muted-foreground opacity-70 group-hover:opacity-100')}
                        aria-label={match.is_locked ? t('schedule.unlockMatch', { team: match.team.name }) : t('schedule.lockMatch', { team: match.team.name })}
                        aria-pressed={match.is_locked}
                    >
                        {match.is_locked ? <Lock className="size-3.5" aria-hidden /> : <LockOpen className="size-3.5" aria-hidden />}
                    </button>
                    <button
                        type="button"
                        onClick={onMoveTo}
                        className="rounded p-0.5 text-muted-foreground opacity-70 hover:bg-black/5 group-hover:opacity-100"
                        aria-label={`${t('schedule.moveTo')} ${match.team.name}`}
                    >
                        <Move className="size-3.5" aria-hidden />
                    </button>
                </div>
            </div>
        </div>
    );
}

interface DraggableCardProps extends Omit<MatchCardProps, 'dragging'> {
    disabled?: boolean;
}

/**
 * Targeta que es pot arrossegar amb el ratolí o el dit (el clic sense moviment obre el panell d'edició).
 * Amb el teclat, cada targeta té l'acció «Mou a…».
 */
export function DraggableMatchCard({ disabled = false, style, ...props }: DraggableCardProps) {
    const { setNodeRef, listeners, isDragging } = useDraggable({ id: `match:${props.match.id}`, disabled, data: { matchId: props.match.id } });

    return (
        <div ref={setNodeRef} {...listeners} style={style} className="touch-none">
            <MatchCard {...props} style={{ height: '100%' }} dragging={isDragging} />
        </div>
    );
}
