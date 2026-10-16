import { useDroppable } from '@dnd-kit/core';
import type { Match } from '@/api/types';
import { minutes } from '@/domain/scheduling/detector';
import type { Area, ScheduleInput, Severity } from '@/domain/scheduling/types';
import { t } from '@/i18n/ca';
import { formatWeekdayDate } from '@/lib/dates';
import { fromMinutes } from '@/lib/time';
import { cn } from '@/lib/utils';
import { cellId, HALF_COLUMNS, offset, ROW, span, type TimeRange } from './layout';
import { DraggableMatchCard, type CardState } from './MatchCard';

export interface DropPreview {
    date: string;
    column: Exclude<Area, 'full'>;
    minute: number;
    /** Durada total d'ocupació del partit arrossegat. */
    occupation: number;
    fullWidth: boolean;
    severity: Severity | 'valid';
}

interface DayCalendarProps {
    date: string;
    input: ScheduleInput;
    range: TimeRange;
    matches: Match[];
    stateOf: (match: Match) => { state: CardState; count: number };
    preview: DropPreview | null;
    onOpen: (match: Match) => void;
    onToggleLock: (match: Match) => void;
    onMoveTo: (match: Match) => void;
}

const COLUMN_LABELS: Record<Exclude<Area, 'full'>, string> = { camp_1: 'Camp 1', camp_2: 'Camp 2' };

const previewColor: Record<Severity | 'valid', string> = {
    critical: 'border-status-critical bg-status-critical/20',
    warning: 'border-status-warning bg-status-warning/20',
    valid: 'border-status-valid bg-status-valid/20',
};

/**
 * Un dia del calendari: eix d'hores, Camp 1 i Camp 2 (els partits de futbol 11 ocupen totes dues columnes),
 * franges habituals, bloquejos i caselles de 15 minuts on es poden deixar els partits.
 */
export function DayCalendar({ date, input, range, matches, stateOf, preview, onOpen, onToggleLock, onMoveTo }: DayCalendarProps) {
    const height = span(range.end - range.start);
    const slots = input.slots.filter((s) => s.date === date);
    const blocks = input.blocks.filter((b) => b.date === date);
    const hours: number[] = [];
    for (let m = range.start; m <= range.end; m += 60) hours.push(m);

    const dayMatches = matches.filter((m) => m.match_date === date && m.kickoff_time !== null);

    return (
        <section aria-label={t('schedule.calendarLabel', { day: formatWeekdayDate(date) })} className="min-w-0 rounded-xl border bg-card shadow-sm">
            <header className="sticky top-0 z-20 grid grid-cols-[3rem_1fr_1fr] rounded-t-xl border-b bg-card">
                <h2 className="col-span-3 px-3 pt-2 text-base font-semibold capitalize">{formatWeekdayDate(date)}</h2>
                <span />
                {HALF_COLUMNS.map((column) => (
                    <span key={column} className="border-l px-2 py-1 text-center text-xs font-medium text-muted-foreground">
                        {COLUMN_LABELS[column]}
                    </span>
                ))}
            </header>
            <div className="grid grid-cols-[3rem_1fr]">
                {/* Eix d'hores */}
                <div className="relative" style={{ height }} aria-hidden>
                    {hours.map((m) => (
                        <span key={m} className={cn('absolute right-1 text-[11px] text-muted-foreground tabular-nums', m === range.start ? 'translate-y-0' : '-translate-y-1/2')} style={{ top: offset(m, range) }}>
                            {fromMinutes(m)}
                        </span>
                    ))}
                </div>
                <div className="relative border-l" style={{ height }}>
                    {/* Línies de les hores */}
                    {hours.map((m) => (
                        <div key={m} className="absolute inset-x-0 border-t border-border/70" style={{ top: offset(m, range) }} aria-hidden />
                    ))}
                    {/* Franges habituals: zona on pot començar un partit */}
                    {slots.map((slot) => (
                        <div
                            key={`${slot.opens}-${slot.last_kickoff}`}
                            className="absolute inset-x-0 bg-secondary/70"
                            style={{ top: offset(minutes(slot.opens), range), height: span(minutes(slot.last_kickoff) - minutes(slot.opens) + 15) }}
                            title={slot.label}
                            aria-hidden
                        />
                    ))}
                    {/* Bloquejos */}
                    {blocks.map((block, index) => {
                        const blockEnd = block.end === '24:00' ? 1440 : minutes(block.end);
                        const top = offset(Math.max(minutes(block.start), range.start), range);
                        const bottom = offset(Math.min(blockEnd, range.end), range);
                        const columnStyle =
                            block.area === 'camp_1' ? { left: 0, width: '50%' } : block.area === 'camp_2' ? { left: '50%', width: '50%' } : { left: 0, right: 0 };

                        return (
                            <div
                                key={index}
                                className="absolute z-[1] flex items-start justify-center border border-status-critical/40 bg-[repeating-linear-gradient(45deg,var(--status-critical-soft),var(--status-critical-soft)_6px,transparent_6px,transparent_12px)] p-1 text-[11px] font-medium text-status-critical"
                                style={{ top, height: Math.max(bottom - top, 0), ...columnStyle }}
                                title={block.reason}
                            >
                                {block.reason}
                            </div>
                        );
                    })}
                    <div className="absolute inset-0 grid grid-cols-2">
                        {HALF_COLUMNS.map((column) => (
                            <Column key={column} date={date} column={column} range={range} />
                        ))}
                    </div>
                    {/* Previsualització del destí mentre s'arrossega */}
                    {preview !== null && preview.date === date && (
                        <div
                            className={cn('pointer-events-none absolute z-30 rounded-md border-2 border-dashed', previewColor[preview.severity])}
                            style={{
                                top: offset(preview.minute, range),
                                height: span(preview.occupation),
                                left: preview.fullWidth || preview.column === 'camp_1' ? '2px' : 'calc(50% + 2px)',
                                width: preview.fullWidth ? 'calc(100% - 4px)' : 'calc(50% - 4px)',
                            }}
                            aria-hidden
                        />
                    )}
                    {/* Partits */}
                    {dayMatches.map((match) => {
                        const start = minutes(match.kickoff_time ?? '00:00');
                        const full = match.game_format === 'f11' || match.field_area?.code === 'full';
                        const left = full || match.field_area?.code !== 'camp_2' ? '2px' : 'calc(50% + 2px)';
                        const occupation = match.duration_min + match.post_min;
                        const { state, count } = stateOf(match);

                        return (
                            <DraggableMatchCard
                                key={match.id}
                                match={match}
                                state={state}
                                conflictCount={count}
                                compact={span(occupation) < ROW * 3}
                                playRatio={match.duration_min / occupation}
                                style={{ position: 'absolute', zIndex: 10, top: offset(start, range) + 1, height: span(occupation) - 2, left, width: full ? 'calc(100% - 4px)' : 'calc(50% - 4px)' }}
                                onOpen={() => onOpen(match)}
                                onToggleLock={() => onToggleLock(match)}
                                onMoveTo={() => onMoveTo(match)}
                            />
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function Column({ date, column, range }: { date: string; column: Exclude<Area, 'full'>; range: TimeRange }) {
    const cells: number[] = [];
    for (let m = range.start; m < range.end; m += 15) cells.push(m);

    return (
        <div className="relative border-l first:border-l-0">
            {cells.map((minute) => (
                <Cell key={minute} id={cellId(date, column, minute)} top={offset(minute, range)} />
            ))}
        </div>
    );
}

function Cell({ id, top }: { id: string; top: number }) {
    const { setNodeRef } = useDroppable({ id });

    return <div ref={setNodeRef} className="absolute inset-x-0" style={{ top, height: ROW }} />;
}
