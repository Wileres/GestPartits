import { useState } from 'react';
import type { Match, ReferenceData } from '@/api/types';
import { NativeSelect } from '@/components/NativeSelect';
import { QuarterTimeSelect } from '@/components/TimeFields';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { previewMove, worstSeverity } from '@/domain/scheduling/detector';
import type { Area, ScheduleInput } from '@/domain/scheduling/types';
import { ca, t } from '@/i18n/ca';
import { formatWeekdayDate } from '@/lib/dates';
import { cn } from '@/lib/utils';

interface MoveDialogProps {
    match: Match | null;
    input: ScheduleInput;
    reference: ReferenceData;
    pending: boolean;
    onMove: (match: Match, target: { date: string; kickoff: string; area: Area } | null) => void;
    onClose: () => void;
}

/**
 * Alternativa accessible a l'arrossegament (teclat i lectors de pantalla): dia, hora i camp,
 * amb la validació al moment del destí.
 */
export function MoveDialog({ match, input, reference, pending, onMove, onClose }: MoveDialogProps) {
    return (
        <Dialog open={match !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                {match !== null && <MoveForm key={match.id} match={match} input={input} reference={reference} pending={pending} onMove={onMove} onClose={onClose} />}
            </DialogContent>
        </Dialog>
    );
}

function MoveForm({ match, input, reference, pending, onMove, onClose }: Omit<MoveDialogProps, 'match'> & { match: Match }) {
    const areas: Area[] = match.game_format === 'f11' ? ['full'] : ['camp_1', 'camp_2', 'full'];
    const [date, setDate] = useState(match.match_date ?? input.dates[0] ?? '');
    const [kickoff, setKickoff] = useState(match.kickoff_time ?? input.operating.first);
    const [area, setArea] = useState<Area>((match.field_area?.code as Area | undefined) ?? areas[0] ?? 'camp_1');

    const conflicts = previewMove(input, match.id, { date, kickoff, area });
    const severity = worstSeverity(conflicts);
    const areaName = (code: Area) => reference.field_areas.find((a) => a.code === code)?.name ?? code;

    return (
        <>
            <DialogHeader>
                <DialogTitle>{t('schedule.moveTitle', { team: match.team.name })}</DialogTitle>
                <DialogDescription>{t('schedule.moveHelp')}</DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="move-day">{t('schedule.day')}</Label>
                    <NativeSelect id="move-day" value={date} onChange={(e) => setDate(e.target.value)}>
                        {input.dates.map((d) => (
                            <option key={d} value={d}>
                                {formatWeekdayDate(d)}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="move-time">{t('schedule.time')}</Label>
                    <QuarterTimeSelect id="move-time" from={input.operating.first} to={input.operating.last} step={input.step_min} value={kickoff} onChange={(e) => setKickoff(e.target.value)} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="move-area">{t('schedule.field')}</Label>
                    <NativeSelect id="move-area" value={area} onChange={(e) => setArea(e.target.value as Area)}>
                        {areas.map((code) => (
                            <option key={code} value={code}>
                                {areaName(code)}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
            </div>
            <div
                role="status"
                className={cn(
                    'rounded-md px-3 py-2 text-sm',
                    severity === 'critical' && 'bg-status-critical-soft text-status-critical',
                    severity === 'warning' && 'bg-status-warning-soft text-status-warning',
                    severity === 'valid' && 'bg-status-valid-soft text-status-valid',
                )}
            >
                <p className="font-medium">{severity === 'critical' ? t('schedule.dropCritical') : severity === 'warning' ? t('schedule.dropWarning') : t('schedule.dropValid')}</p>
                {conflicts.length > 0 && (
                    <ul className="mt-1 list-disc pl-5">
                        {conflicts.map((c, i) => (
                            <li key={`${c.code}-${i}`}>{ca.conflicts[c.code]}</li>
                        ))}
                    </ul>
                )}
            </div>
            <DialogFooter className="flex-wrap gap-2">
                {match.kickoff_time !== null && (
                    <Button type="button" variant="ghost" onClick={() => onMove(match, null)} disabled={pending}>
                        {t('schedule.moveToTray')}
                    </Button>
                )}
                <Button type="button" variant="outline" onClick={onClose}>
                    {t('common.cancel')}
                </Button>
                <Button type="button" onClick={() => onMove(match, { date, kickoff, area })} disabled={pending}>
                    {t('schedule.moveSubmit')}
                </Button>
            </DialogFooter>
        </>
    );
}
