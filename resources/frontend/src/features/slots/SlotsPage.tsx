import { CalendarPlus, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { AvailabilitySlot } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useReferenceData } from '@/hooks/useReferenceData';
import { t } from '@/i18n/ca';
import { formatWeekdayDate, weekdayName } from '@/lib/dates';
import { errorMessage } from '@/lib/errors';
import { SlotFormDialog } from './SlotFormDialog';
import { useDeleteSlot, useSlots } from './useSlots';

export function SlotsPage() {
    const { data: slots, isPending, isError } = useSlots();
    const { data: reference } = useReferenceData();
    const deleteSlot = useDeleteSlot();
    const [form, setForm] = useState<{ open: boolean; slot: AvailabilitySlot | null; kind: 'weekly' | 'date' }>({ open: false, slot: null, kind: 'weekly' });
    const [deleting, setDeleting] = useState<AvailabilitySlot | null>(null);

    const weekly = slots?.filter((s) => s.weekday !== null) ?? [];
    const exceptions = slots?.filter((s) => s.specific_date !== null) ?? [];
    const hours = reference?.operating_hours;

    const row = (slot: AvailabilitySlot) => (
        <li key={slot.id} className="flex flex-wrap items-center gap-3 py-3">
            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2 font-medium">
                    {slot.label}
                    {!slot.is_active && <Badge variant="outline">{t('slots.inactive')}</Badge>}
                </p>
                <p className="text-sm text-muted-foreground">
                    {slot.weekday !== null ? weekdayName(slot.weekday) : formatWeekdayDate(slot.specific_date ?? '')} · {slot.opens_at} – {slot.last_kickoff_at}
                </p>
                <p className="text-xs text-muted-foreground">
                    {slot.default_fill_order === null ? t('slots.notUsedForFill') : `${t('slots.fillOrder')}: ${slot.default_fill_order}`}
                </p>
            </div>
            <Button variant="ghost" size="icon" onClick={() => setForm({ open: true, slot, kind: slot.weekday === null ? 'date' : 'weekly' })} aria-label={`${t('common.edit')}: ${slot.label}`}>
                <Pencil aria-hidden />
            </Button>
            <Button variant="ghost" size="icon" onClick={() => setDeleting(slot)} aria-label={`${t('common.delete')}: ${slot.label}`}>
                <Trash2 className="text-destructive" aria-hidden />
            </Button>
        </li>
    );

    return (
        <>
            <PageHeader title={t('slots.title')} description={hours === undefined ? undefined : t('slots.subtitle', { from: hours.first_kickoff, to: hours.last_kickoff })} />
            {isError && <ErrorState />}
            {isPending || reference === undefined ? (
                <LoadingState />
            ) : (
                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                            <CardTitle>
                                <h2>{t('slots.weekly')}</h2>
                            </CardTitle>
                            <Button size="sm" onClick={() => setForm({ open: true, slot: null, kind: 'weekly' })}>
                                <Plus aria-hidden />
                                {t('slots.create')}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">{weekly.map(row)}</ul>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                            <div>
                                <CardTitle>
                                    <h2>{t('slots.exceptions')}</h2>
                                </CardTitle>
                                <CardDescription>{t('slots.exceptionsHelp')}</CardDescription>
                            </div>
                            <Button size="sm" variant="outline" onClick={() => setForm({ open: true, slot: null, kind: 'date' })}>
                                <CalendarPlus aria-hidden />
                                {t('slots.createException')}
                            </Button>
                        </CardHeader>
                        <CardContent>{exceptions.length === 0 ? <EmptyState title={t('slots.noExceptions')} /> : <ul className="divide-y">{exceptions.map(row)}</ul>}</CardContent>
                    </Card>
                </div>
            )}
            {reference !== undefined && (
                <SlotFormDialog open={form.open} slot={form.slot} defaultKind={form.kind} reference={reference} onOpenChange={(open) => setForm((f) => ({ ...f, open }))} />
            )}
            <ConfirmDialog
                open={deleting !== null}
                title={t('slots.deleteConfirmTitle', { name: deleting?.label ?? '' })}
                pending={deleteSlot.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() =>
                    deleting !== null &&
                    deleteSlot.mutate(deleting.id, {
                        onSuccess: () => {
                            toast.success(t('slots.deleted'));
                            setDeleting(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </>
    );
}
