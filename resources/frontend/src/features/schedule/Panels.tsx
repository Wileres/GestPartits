import { useDroppable } from '@dnd-kit/core';
import { Bus, CircleCheck, Inbox, OctagonAlert, TriangleAlert } from 'lucide-react';
import type { PresentedConflict, ProposalSummary } from '@/api/schedule';
import type { Match } from '@/api/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/i18n/ca';
import { formatWeekdayDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { TRAY_ID } from './layout';
import { DraggableMatchCard, type CardState } from './MatchCard';

interface TrayProps {
    matches: Match[];
    proposal: ProposalSummary | null;
    stateOf: (match: Match) => { state: CardState; count: number };
    onOpen: (match: Match) => void;
    onToggleLock: (match: Match) => void;
    onMoveTo: (match: Match) => void;
}

/** «Partits sense programar»: també és un destí per treure un partit del calendari. */
export function UnscheduledTray({ matches, proposal, stateOf, onOpen, onToggleLock, onMoveTo }: TrayProps) {
    const { setNodeRef, isOver } = useDroppable({ id: TRAY_ID });

    return (
        <Card ref={setNodeRef} className={cn('gap-3 transition-colors', isOver && 'border-primary bg-secondary')} aria-labelledby="titol-sense-programar">
            <CardHeader>
                <CardTitle>
                    <h2 id="titol-sense-programar" className="flex items-center gap-2">
                        <Inbox className="size-4" aria-hidden />
                        {t('schedule.unscheduled')} ({matches.length})
                    </h2>
                </CardTitle>
                <p className="text-xs text-muted-foreground">{t('schedule.unscheduledHelp')}</p>
            </CardHeader>
            <CardContent className="grid gap-2">
                {matches.length === 0 && <p className="text-sm text-muted-foreground">{t('schedule.unscheduledEmpty')}</p>}
                {matches.map((match) => {
                    const { state, count } = stateOf(match);
                    const reason = proposal?.unplaced[String(match.id)]?.message;

                    return (
                        <div key={match.id} className="grid gap-1">
                            <DraggableMatchCard match={match} state={state} conflictCount={count} style={{ height: 58 }} onOpen={() => onOpen(match)} onToggleLock={() => onToggleLock(match)} onMoveTo={() => onMoveTo(match)} />
                            {reason !== undefined && <p className="text-xs text-status-warning">{reason}</p>}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

export function AwayList({ dates, matches, onOpen }: { dates: string[]; matches: Match[]; onOpen: (match: Match) => void }) {
    return (
        <Card className="gap-3">
            <CardHeader>
                <CardTitle>
                    <h2 className="flex items-center gap-2">
                        <Bus className="size-4" aria-hidden />
                        {t('schedule.away')} ({matches.length})
                    </h2>
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3">
                {matches.length === 0 && <p className="text-sm text-muted-foreground">{t('schedule.awayEmpty')}</p>}
                {[...dates, null].map((date) => {
                    const list = matches
                        .filter((m) => m.match_date === date)
                        .sort((a, b) => (a.kickoff_time ?? '99').localeCompare(b.kickoff_time ?? '99'));
                    if (list.length === 0) return null;

                    return (
                        <div key={date ?? 'sense-data'}>
                            <h3 className="mb-1 text-xs font-semibold text-muted-foreground capitalize">{date === null ? t('schedule.noTime') : formatWeekdayDate(date)}</h3>
                            <ul className="grid gap-1">
                                {list.map((match) => (
                                    <li key={match.id}>
                                        <button type="button" onClick={() => onOpen(match)} className="w-full rounded-md border px-2 py-1.5 text-left text-sm hover:bg-muted">
                                            <span className="font-medium tabular-nums">{match.kickoff_time ?? '--:--'}</span> {match.team.name} – {match.opponent ?? '—'}
                                            {(match.venue_name ?? match.municipality) !== null && (
                                                <span className="block text-xs text-muted-foreground">{[match.venue_name, match.municipality].filter(Boolean).join(' · ')}</span>
                                            )}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

export function ConflictPanel({ conflicts }: { conflicts: PresentedConflict[] }) {
    const critical = conflicts.filter((c) => c.severity === 'critical');
    const warnings = conflicts.filter((c) => c.severity === 'warning');

    return (
        <Card className="gap-3" aria-live="polite">
            <CardHeader>
                <CardTitle>
                    <h2>{t('schedule.conflicts')}</h2>
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3">
                {conflicts.length === 0 && (
                    <p className="flex items-center gap-2 text-sm text-status-valid">
                        <CircleCheck className="size-4" aria-hidden />
                        {t('schedule.noConflicts')}
                    </p>
                )}
                {critical.length > 0 && <ConflictList title={t('schedule.critical')} conflicts={critical} severity="critical" />}
                {warnings.length > 0 && <ConflictList title={t('schedule.warnings')} conflicts={warnings} severity="warning" />}
            </CardContent>
        </Card>
    );
}

function ConflictList({ title, conflicts, severity }: { title: string; conflicts: PresentedConflict[]; severity: 'critical' | 'warning' }) {
    const Icon = severity === 'critical' ? OctagonAlert : TriangleAlert;

    return (
        <div>
            <h3 className={cn('mb-1 text-xs font-semibold', severity === 'critical' ? 'text-status-critical' : 'text-status-warning')}>
                {title} ({conflicts.length})
            </h3>
            <ul className="grid gap-1">
                {conflicts.map((conflict, index) => (
                    <li
                        key={`${conflict.code}-${conflict.match_ids.join('-')}-${index}`}
                        className={cn('flex gap-2 rounded-md px-2 py-1.5 text-sm', severity === 'critical' ? 'bg-status-critical-soft' : 'bg-status-warning-soft')}
                    >
                        <Icon className={cn('mt-0.5 size-4 shrink-0', severity === 'critical' ? 'text-status-critical' : 'text-status-warning')} aria-hidden />
                        {conflict.message}
                    </li>
                ))}
            </ul>
        </div>
    );
}
