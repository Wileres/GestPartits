import { useQuery } from '@tanstack/react-query';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { scheduleApi, type PlacementSummary, type PresentedConflict, type ProposalSummary, type ScheduleData, type VersionChange } from '@/api/schedule';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { LoadingState } from '@/components/QueryState';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, t } from '@/i18n/ca';
import { formatWeekdayDate } from '@/lib/dates';

function describe(p: PlacementSummary | null): string {
    if (p === null || p.kickoff_time === null || p.match_date === null) return t('schedule.noTime');

    return `${formatWeekdayDate(p.match_date)} ${p.kickoff_time}${p.field_area === null ? '' : ` · ${p.field_area}`}`;
}

function ChangesTable({ changes }: { changes: VersionChange[] }) {
    if (changes.length === 0) return <p className="text-sm text-muted-foreground">{t('schedule.proposalNoChanges')}</p>;

    return (
        <div className="max-h-80 overflow-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('matches.team')}</TableHead>
                        <TableHead>{t('schedule.before')}</TableHead>
                        <TableHead aria-hidden />
                        <TableHead>{t('schedule.after')}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {changes.map((change) => (
                        <TableRow key={change.match_id}>
                            <TableCell className="font-medium">
                                {change.team}
                                {change.opponent !== null && <span className="block text-xs font-normal text-muted-foreground">{change.opponent}</span>}
                            </TableCell>
                            <TableCell className="text-sm">{describe(change.from)}</TableCell>
                            <TableCell aria-hidden>
                                <ArrowRight className="size-4 text-muted-foreground" />
                            </TableCell>
                            <TableCell className="text-sm font-medium">{describe(change.to)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

/** Resum després de generar una proposta: comparació amb la situació anterior. */
export function ProposalDialog({ proposal, teamName, onClose }: { proposal: ProposalSummary | null; teamName: (id: number) => string; onClose: () => void }) {
    const unplaced = Object.entries(proposal?.unplaced ?? {});

    return (
        <Dialog open={proposal !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('schedule.proposalTitle')}</DialogTitle>
                    <DialogDescription>{t('schedule.proposalPlaced', { count: proposal?.placed.length ?? 0 })}</DialogDescription>
                </DialogHeader>
                {unplaced.length > 0 && (
                    <div className="rounded-md bg-status-warning-soft p-3 text-sm text-status-warning">
                        <p className="font-medium">{t('schedule.proposalUnplaced')}</p>
                        <ul className="mt-1 list-disc pl-5">
                            {unplaced.map(([id, info]) => (
                                <li key={id}>
                                    {teamName(Number(id))}: {info.message}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {(proposal?.relocated.length ?? 0) > 0 && <p className="text-sm text-muted-foreground">{t('schedule.proposalRelocated')}</p>}
                <h3 className="text-sm font-semibold">{t('schedule.proposalChanges')}</h3>
                <ChangesTable changes={proposal?.changes ?? []} />
                <DialogFooter>
                    <Button onClick={onClose}>{t('common.close')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function VersionsDialog({
    open,
    schedule,
    pending,
    onRestore,
    onClose,
}: {
    open: boolean;
    schedule: ScheduleData;
    pending: boolean;
    onRestore: (versionId: number) => void;
    onClose: () => void;
}) {
    const [toRestore, setToRestore] = useState<ScheduleData['versions'][number] | null>(null);
    const versions = [...schedule.versions].reverse();
    const [selection, setSelection] = useState<{ from: number | null; to: number | null }>({ from: null, to: null });
    const from = selection.from ?? versions[1]?.id ?? null;
    const to = selection.to ?? versions[0]?.id ?? null;
    const comparison = useQuery({
        queryKey: ['schedules', schedule.id, 'compare', from, to],
        queryFn: () => scheduleApi.compare(schedule.id, from ?? 0, to ?? 0),
        enabled: open && from !== null && to !== null && from !== to,
    });

    return (
        <>
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('schedule.versionsTitle')}</DialogTitle>
                    <DialogDescription>{t('schedule.versionsHelp')}</DialogDescription>
                </DialogHeader>
                <div className="max-h-64 overflow-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('schedule.before')}</TableHead>
                                <TableHead>{t('schedule.after')}</TableHead>
                                <TableHead>{t('schedule.versions')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {versions.map((version) => (
                                <TableRow key={version.id}>
                                    <TableCell>
                                        <input
                                            type="radio"
                                            name="versio-abans"
                                            className="accent-primary"
                                            checked={from === version.id}
                                            onChange={() => setSelection((s) => ({ ...s, from: version.id }))}
                                            aria-label={`${t('schedule.before')}: ${version.number}`}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <input
                                            type="radio"
                                            name="versio-despres"
                                            className="accent-primary"
                                            checked={to === version.id}
                                            onChange={() => setSelection((s) => ({ ...s, to: version.id }))}
                                            aria-label={`${t('schedule.after')}: ${version.number}`}
                                        />
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        <span className="font-medium">
                                            {version.number}. {version.summary ?? version.kind_label}
                                        </span>
                                        {version.is_current && <span className="ml-2 text-xs text-primary">({t('schedule.current')})</span>}
                                        <span className="block text-xs text-muted-foreground">
                                            {version.created_by ?? '—'} · {formatDateTime(version.created_at)}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {!version.is_current && (
                                            <Button variant="outline" size="sm" disabled={pending} onClick={() => setToRestore(version)} aria-label={`${t('schedule.restore')} ${version.number}`}>
                                                {t('schedule.restore')}
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {comparison.isFetching ? <LoadingState /> : comparison.data !== undefined && <ChangesTable changes={comparison.data} />}
                <DialogFooter>
                    <Button onClick={onClose}>{t('common.close')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <ConfirmDialog
            open={toRestore !== null}
            title={t('schedule.restoreTitle')}
            description={`${t('schedule.restoreHelp')} ${toRestore?.kind === 'confirmation' ? '' : t('schedule.restoreHelpDraft')}`.trim()}
            confirmLabel={t('schedule.restore')}
            pending={pending}
            onCancel={() => setToRestore(null)}
            onConfirm={() => {
                if (toRestore !== null) onRestore(toRestore.id);
                setToRestore(null);
            }}
        />
        </>
    );
}

export function ConfirmScheduleDialog({
    open,
    blocking,
    pending,
    onConfirm,
    onClose,
}: {
    open: boolean;
    blocking: PresentedConflict[];
    pending: boolean;
    onConfirm: () => void;
    onClose: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('schedule.confirmTitle')}</DialogTitle>
                    <DialogDescription>{blocking.length === 0 ? t('schedule.confirmHelp') : t('schedule.cannotConfirm')}</DialogDescription>
                </DialogHeader>
                {blocking.length > 0 && (
                    <ul className="grid gap-1">
                        {blocking.map((c, i) => (
                            <li key={`${c.code}-${i}`} className="rounded-md bg-status-critical-soft px-2 py-1.5 text-sm text-status-critical">
                                {c.message}
                            </li>
                        ))}
                    </ul>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button onClick={onConfirm} disabled={blocking.length > 0 || pending}>
                        {t('schedule.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
