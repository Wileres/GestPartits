import { DndContext, DragOverlay, PointerSensor, TouchSensor, useSensor, useSensors, type DragEndEvent, type DragOverEvent, type DragStartEvent } from '@dnd-kit/core';
import { CalendarDays, CheckCircle2, History, ImageIcon, MessageCircle, Save, Sparkles, Undo2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { toast } from 'sonner';
import { ApiError } from '@/api/client';
import type { ProposalSummary, ScheduleData } from '@/api/schedule';
import type { Match, ReferenceData, Team } from '@/api/types';
import { MatchdaySelector } from '@/components/MatchdaySelector';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { previewMove, worstSeverity } from '@/domain/scheduling/detector';
import type { Area } from '@/domain/scheduling/types';
import { ImagesDialog } from '@/features/images/ImagesDialog';
import { MatchSheet, type MatchSheetTarget } from '@/features/matches/MatchSheet';
import { useTeams } from '@/features/teams/useTeams';
import { useMatchdaySelection } from '@/hooks/useMatchdaySelection';
import { useReferenceData } from '@/hooks/useReferenceData';
import { formatDateTime, t } from '@/i18n/ca';
import { catalanAnnouncements } from '@/lib/dnd';
import { fromMinutes } from '@/lib/time';
import { cn } from '@/lib/utils';
import { DayCalendar, type DropPreview } from './DayCalendar';
import { ConfirmScheduleDialog, ProposalDialog, VersionsDialog } from './Dialogs';
import { isHomeActive, parseCellId, TRAY_ID, visibleRange } from './layout';
import { MatchCard, type CardState } from './MatchCard';
import { MoveDialog } from './MoveDialog';
import { AwayList, ConflictPanel, UnscheduledTray } from './Panels';
import { useSchedule, useScheduleActions } from './useSchedule';

export function SchedulePage() {
    const selection = useMatchdaySelection();
    const { data: reference } = useReferenceData();

    return (
        <>
            <PageHeader title={t('schedule.title')} description={t('schedule.subtitle')} />
            <div className="mb-4">
                <MatchdaySelector selection={selection} />
            </div>
            {selection.isPending || reference === undefined ? (
                <LoadingState />
            ) : selection.matchday === null ? (
                <EmptyState icon={<CalendarDays className="size-8 text-muted-foreground" aria-hidden />} title={t('schedule.emptyTitle')}>
                    <p className="text-sm text-muted-foreground">{t('schedule.emptyHelp')}</p>
                </EmptyState>
            ) : (
                <ScheduleBoard key={selection.matchday.id} matchdayId={selection.matchday.id} reference={reference} />
            )}
        </>
    );
}

const statusBadge: Record<ScheduleData['status'], string> = {
    draft: 'border-muted-foreground/30 bg-muted text-muted-foreground',
    proposed: 'border-primary/30 bg-secondary text-primary',
    confirmed: 'border-status-locked/40 bg-status-locked-soft text-status-locked',
    needs_review: 'border-status-warning/40 bg-status-warning-soft text-status-warning',
};

function ScheduleBoard({ matchdayId, reference }: { matchdayId: number; reference: ReferenceData }) {
    const { data: schedule, isPending, isError } = useSchedule(matchdayId);
    const { data: teams = [] } = useTeams();
    const actions = useScheduleActions(matchdayId, schedule);

    const [dragging, setDragging] = useState<Match | null>(null);
    const [preview, setPreview] = useState<DropPreview | null>(null);
    const [sheet, setSheet] = useState<MatchSheetTarget | null>(null);
    const [moving, setMoving] = useState<Match | null>(null);
    const [proposal, setProposal] = useState<ProposalSummary | null>(null);
    const [versionsOpen, setVersionsOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [imagesOpen, setImagesOpen] = useState(false);
    const navigate = useNavigate();

    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(TouchSensor, { activationConstraint: { delay: 250, tolerance: 8 } }));

    const conflictsByMatch = useMemo(() => {
        const map = new Map<number, { state: CardState; count: number }>();
        for (const conflict of schedule?.conflicts ?? []) {
            for (const id of conflict.match_ids) {
                const current = map.get(id) ?? { state: 'valid' as CardState, count: 0 };
                map.set(id, { state: conflict.severity === 'critical' || current.state === 'critical' ? 'critical' : 'warning', count: current.count + 1 });
            }
        }

        return map;
    }, [schedule?.conflicts]);

    if (isPending) return <LoadingState />;
    if (isError || schedule === undefined) return <ErrorState />;

    if (schedule.matches.length === 0) {
        return (
            <EmptyState title={t('schedule.noMatches')}>
                <Button asChild size="sm">
                    <Link to={`/partits?jornada=${matchdayId}`}>{t('schedule.goToMatches')}</Link>
                </Button>
            </EmptyState>
        );
    }

    const homeMatches = schedule.matches.filter(isHomeActive);
    const placed = homeMatches.filter((m) => m.kickoff_time !== null && m.match_date !== null);
    const unscheduled = homeMatches.filter((m) => m.kickoff_time === null || m.match_date === null);
    const away = schedule.matches.filter((m) => m.home_away === 'away' && ['pending', 'proposed', 'confirmed'].includes(m.status));
    const range = visibleRange(schedule.input, placed);
    const stateOf = (match: Match) => conflictsByMatch.get(match.id) ?? { state: 'valid' as CardState, count: 0 };
    const areaId = (code: Area) => reference.field_areas.find((a) => a.code === code)?.id ?? null;
    const teamOf = (match: Match): Team | undefined => teams.find((team) => team.id === match.team_id);
    const busy = actions.propose.isPending || actions.save.isPending || actions.undo.isPending || actions.confirm.isPending || actions.move.isPending;

    const openSheet = (match: Match) => {
        const team = teamOf(match);
        if (team !== undefined) setSheet({ team, match });
    };

    const targetFor = (match: Match, overId: string | null) => {
        if (overId === null) return undefined;
        if (overId === TRAY_ID) return null;
        const cell = parseCellId(overId);
        if (cell === null) return undefined;

        return { date: cell.date, kickoff: fromMinutes(cell.minute), area: (match.game_format === 'f11' ? 'full' : cell.column) as Area, cell };
    };

    const onDragStart = ({ active }: DragStartEvent) => {
        setDragging(schedule.matches.find((m) => m.id === active.data.current?.matchId) ?? null);
    };

    const onDragOver = ({ over }: DragOverEvent) => {
        if (dragging === null) return;
        const target = targetFor(dragging, over === null ? null : String(over.id));
        if (target === undefined || target === null) {
            setPreview(null);

            return;
        }

        const severity = worstSeverity(previewMove(schedule.input, dragging.id, target));
        setPreview({
            date: target.date,
            column: target.cell.column,
            minute: target.cell.minute,
            occupation: dragging.duration_min + dragging.post_min,
            fullWidth: target.area === 'full',
            severity,
        });
    };

    const onDragEnd = ({ over }: DragEndEvent) => {
        const match = dragging;
        setDragging(null);
        setPreview(null);
        if (match === null) return;

        const target = targetFor(match, over === null ? null : String(over.id));
        if (target === undefined) return;

        moveTo(match, target === null ? null : target);
    };

    const moveTo = (match: Match, target: { date: string; kickoff: string; area: Area } | null) => {
        if (target === null && match.kickoff_time === null) return;
        if (target !== null && match.match_date === target.date && match.kickoff_time === target.kickoff && match.field_area?.code === target.area) return;

        actions.move.mutate(
            {
                matchId: match.id,
                target: target === null ? { match_date: null, kickoff_time: null, field_area_id: null } : { match_date: target.date, kickoff_time: target.kickoff, field_area_id: areaId(target.area) },
            },
            {
                onSuccess: () => {
                    setMoving(null);
                    toast.success(t('schedule.moved'), { id: 'schedule-moved' });
                },
            },
        );
    };

    const announcements = catalanAnnouncements((id) => {
        const value = String(id);
        if (value.startsWith('match:')) return schedule.matches.find((m) => `match:${m.id}` === value)?.team.name ?? value;
        if (value === TRAY_ID) return t('schedule.unscheduled');
        const cell = parseCellId(value);

        return cell === null ? value : `${fromMinutes(cell.minute)} ${cell.column === 'camp_1' ? 'Camp 1' : 'Camp 2'}`;
    });

    return (
        <>
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <Badge variant="outline" className={cn('text-sm', statusBadge[schedule.status])}>
                    {t(`schedule.statusLabels.${schedule.status}`)}
                </Badge>
                {schedule.has_unsaved_changes && <Badge variant="outline">{t('schedule.unsaved')}</Badge>}
                {schedule.status === 'confirmed' && schedule.confirmed_by !== null && (
                    <span className="text-xs text-muted-foreground">{t('schedule.confirmedBy', { name: schedule.confirmed_by, date: formatDateTime(schedule.confirmed_at) })}</span>
                )}
                <div className="ml-auto flex flex-wrap gap-2">
                    <Button
                        onClick={() =>
                            actions.propose.mutate(undefined, {
                                onSuccess: (data) => setProposal(data.proposal),
                            })
                        }
                        disabled={busy}
                    >
                        <Sparkles aria-hidden />
                        {t('schedule.propose')}
                    </Button>
                    <Button variant="outline" onClick={() => actions.undo.mutate(undefined, { onSuccess: () => toast.success(t('schedule.undone')) })} disabled={busy || schedule.versions.length === 0}>
                        <Undo2 aria-hidden />
                        {t('schedule.undo')}
                    </Button>
                    <Button variant="outline" onClick={() => actions.save.mutate(undefined, { onSuccess: () => toast.success(t('schedule.saved')) })} disabled={busy}>
                        <Save aria-hidden />
                        {t('schedule.save')}
                    </Button>
                    <Button variant="outline" onClick={() => setVersionsOpen(true)} disabled={schedule.versions.length < 2}>
                        <History aria-hidden />
                        {t('schedule.versions')}
                    </Button>
                    <Button
                        variant={schedule.can_confirm ? 'default' : 'outline'}
                        className={cn(schedule.can_confirm && 'bg-status-valid hover:bg-status-valid/90')}
                        onClick={() => setConfirmOpen(true)}
                        disabled={busy || schedule.status === 'confirmed'}
                    >
                        <CheckCircle2 aria-hidden />
                        {t('schedule.confirm')}
                    </Button>
                    <Button variant="outline" onClick={() => setImagesOpen(true)} disabled={schedule.status !== 'confirmed'} title={schedule.status !== 'confirmed' ? t('images.needsConfirmation') : undefined}>
                        <ImageIcon aria-hidden />
                        {t('images.open')}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => void navigate(`/avisos?jornada=${matchdayId}`)}
                        disabled={schedule.status !== 'confirmed'}
                        title={schedule.status !== 'confirmed' ? t('images.needsConfirmation') : undefined}
                    >
                        <MessageCircle aria-hidden />
                        {t('notifications.open')}
                    </Button>
                </div>
            </div>

            <Legend />

            <DndContext sensors={sensors} onDragStart={onDragStart} onDragOver={onDragOver} onDragEnd={onDragEnd} onDragCancel={() => (setDragging(null), setPreview(null))} accessibility={announcements}>
                <div className="grid gap-4 lg:grid-cols-2">
                    {schedule.input.dates.map((date) => (
                        <DayCalendar
                            key={date}
                            date={date}
                            input={schedule.input}
                            range={range}
                            matches={placed}
                            stateOf={stateOf}
                            preview={preview}
                            onOpen={openSheet}
                            onToggleLock={(match) => actions.toggleLock.mutate({ matchId: match.id, locked: !match.is_locked })}
                            onMoveTo={setMoving}
                        />
                    ))}
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-3">
                    <UnscheduledTray
                        matches={unscheduled}
                        proposal={schedule.proposal ?? proposal}
                        stateOf={stateOf}
                        onOpen={openSheet}
                        onToggleLock={(match) => actions.toggleLock.mutate({ matchId: match.id, locked: !match.is_locked })}
                        onMoveTo={setMoving}
                    />
                    <AwayList dates={schedule.input.dates} matches={away} onOpen={openSheet} />
                    <ConflictPanel conflicts={schedule.conflicts} />
                </div>

                <DragOverlay dropAnimation={null}>
                    {dragging !== null && (
                        <div className="w-44" style={{ height: 58 }}>
                            <MatchCard match={dragging} state={preview?.severity ?? stateOf(dragging).state} conflictCount={0} style={{ height: '100%' }} onOpen={() => undefined} onToggleLock={() => undefined} onMoveTo={() => undefined} />
                        </div>
                    )}
                </DragOverlay>
            </DndContext>

            <p className="sr-only" aria-live="assertive">
                {preview === null ? '' : preview.severity === 'critical' ? t('schedule.dropCritical') : preview.severity === 'warning' ? t('schedule.dropWarning') : t('schedule.dropValid')}
            </p>

            <MoveDialog match={moving} input={schedule.input} reference={reference} pending={actions.move.isPending} onMove={moveTo} onClose={() => setMoving(null)} />
            <MatchSheet target={sheet} matchday={schedule.matchday} reference={reference} onClose={() => setSheet(null)} />
            <ProposalDialog proposal={proposal} teamName={(id) => schedule.matches.find((m) => m.id === id)?.team.name ?? String(id)} onClose={() => setProposal(null)} />
            <VersionsDialog open={versionsOpen} schedule={schedule} onClose={() => setVersionsOpen(false)} />
            <ImagesDialog scheduleId={schedule.id} open={imagesOpen} onClose={() => setImagesOpen(false)} />
            <ConfirmScheduleDialog
                open={confirmOpen}
                blocking={schedule.blocking_conflicts}
                pending={actions.confirm.isPending}
                onClose={() => setConfirmOpen(false)}
                onConfirm={() =>
                    actions.confirm.mutate(undefined, {
                        onSuccess: () => {
                            setConfirmOpen(false);
                            toast.success(t('schedule.confirmed'));
                        },
                        onError: (error) => {
                            if (error instanceof ApiError && error.status === 423) toast.error(t('schedule.cannotConfirm'));
                        },
                    })
                }
            />
        </>
    );
}

function Legend() {
    const items: [string, string][] = [
        ['border-status-critical bg-status-critical-soft', t('schedule.legendCritical')],
        ['border-status-warning bg-status-warning-soft', t('schedule.legendWarning')],
        ['border-status-valid bg-card', t('schedule.legendValid')],
        ['border-status-locked ring-2 ring-status-locked/50', t('schedule.legendLocked')],
        ['border-transparent bg-secondary', t('schedule.legendSlot')],
        ['border-status-critical/40 bg-[repeating-linear-gradient(45deg,var(--status-critical-soft),var(--status-critical-soft)_3px,transparent_3px,transparent_6px)]', t('schedule.legendBlock')],
    ];

    return (
        <ul className="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {items.map(([style, label]) => (
                <li key={label} className="flex items-center gap-1.5">
                    <span className={cn('inline-block size-3 rounded-sm border-2', style)} aria-hidden />
                    {label}
                </li>
            ))}
        </ul>
    );
}
