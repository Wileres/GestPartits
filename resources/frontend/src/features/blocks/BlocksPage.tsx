import { zodResolver } from '@hookform/resolvers/zod';
import { Ban, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { FieldBlock, ReferenceData } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DateTextInput } from '@/components/DateTextInput';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { QuarterTimeSelect } from '@/components/TimeFields';
import { Input } from '@/components/ui/input';
import { useBlocks, useDeleteBlock, useSaveBlock } from '@/features/slots/useSlots';
import { useReferenceData } from '@/hooks/useReferenceData';
import { t } from '@/i18n/ca';
import { displayToIso, requiredDisplayDate } from '@/lib/dateSchema';
import { formatLocalDateTime, toDisplayDate } from '@/lib/dates';
import { errorMessage, numberOrNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';

const schema = z
    .object({
        field_area_id: z.string(),
        start_date: requiredDisplayDate,
        start_time: z.string().min(1, t('common.required')),
        end_date: requiredDisplayDate,
        end_time: z.string().min(1, t('common.required')),
        reason: z.string().trim().min(1, t('common.required')),
    })
    .refine((v) => `${displayToIso(v.end_date) ?? ''}T${v.end_time}` > `${displayToIso(v.start_date) ?? ''}T${v.start_time}`, {
        message: t('common.endBeforeStart'),
        path: ['end_time'],
    });

type FormValues = z.infer<typeof schema>;

export function BlocksPage() {
    const { data: blocks, isPending, isError } = useBlocks();
    const { data: reference } = useReferenceData();
    const deleteBlock = useDeleteBlock();
    const [form, setForm] = useState<{ open: boolean; block: FieldBlock | null }>({ open: false, block: null });
    const [deleting, setDeleting] = useState<FieldBlock | null>(null);

    return (
        <>
            <PageHeader
                title={t('blocks.title')}
                description={t('blocks.subtitle')}
                actions={
                    <Button onClick={() => setForm({ open: true, block: null })}>
                        <Plus aria-hidden />
                        {t('blocks.create')}
                    </Button>
                }
            />
            {isError && <ErrorState />}
            {isPending ? (
                <LoadingState />
            ) : blocks === undefined || blocks.length === 0 ? (
                <EmptyState icon={<Ban className="size-8 text-muted-foreground" aria-hidden />} title={t('blocks.empty')} />
            ) : (
                <ul className="grid gap-2">
                    {blocks.map((block) => (
                        <li key={block.id}>
                            <Card className="flex-row items-center gap-3 border-status-critical/30 px-4 py-3">
                                <Ban className="size-5 shrink-0 text-status-critical" aria-hidden />
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">{block.reason}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {block.field_area?.name ?? t('blocks.wholeField')} · {formatLocalDateTime(block.starts_at)} – {formatLocalDateTime(block.ends_at)}
                                    </p>
                                </div>
                                <Button variant="ghost" size="icon" onClick={() => setForm({ open: true, block })} aria-label={`${t('common.edit')}: ${block.reason}`}>
                                    <Pencil aria-hidden />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => setDeleting(block)} aria-label={`${t('common.delete')}: ${block.reason}`}>
                                    <Trash2 className="text-destructive" aria-hidden />
                                </Button>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
            <Dialog open={form.open} onOpenChange={(open) => setForm((f) => ({ ...f, open }))}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{form.block === null ? t('blocks.create') : t('blocks.edit')}</DialogTitle>
                    </DialogHeader>
                    {form.open && reference !== undefined && <BlockForm block={form.block} reference={reference} onDone={() => setForm({ open: false, block: null })} />}
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                open={deleting !== null}
                title={t('blocks.deleteConfirmTitle')}
                description={deleting?.reason}
                pending={deleteBlock.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() =>
                    deleting !== null &&
                    deleteBlock.mutate(deleting.id, {
                        onSuccess: () => {
                            toast.success(t('blocks.deleted'));
                            setDeleting(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </>
    );
}

function BlockForm({ block, reference, onDone }: { block: FieldBlock | null; reference: ReferenceData; onDone: () => void }) {
    const save = useSaveBlock();
    const [formError, setFormError] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: {
            field_area_id: block?.field_area_id?.toString() ?? '',
            start_date: toDisplayDate(block?.starts_at.slice(0, 10)),
            start_time: block?.starts_at.slice(11, 16) ?? '09:00',
            end_date: toDisplayDate(block?.ends_at.slice(0, 10)),
            end_time: block?.ends_at.slice(11, 16) ?? '13:00',
            reason: block?.reason ?? '',
        },
    });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            {
                id: block?.id ?? null,
                input: {
                    field_area_id: numberOrNull(values.field_area_id),
                    starts_at: `${displayToIso(values.start_date) ?? ''}T${values.start_time}`,
                    ends_at: `${displayToIso(values.end_date) ?? ''}T${values.end_time}`,
                    reason: values.reason,
                },
            },
            {
                onSuccess: () => {
                    toast.success(t('blocks.saved'));
                    onDone();
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, ['field_area_id', 'reason'] as const)),
            },
        );
    });

    return (
        <form onSubmit={onSubmit} noValidate className="grid gap-4">
            {formError !== null && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{formError}</AlertDescription>
                </Alert>
            )}
            <FormField id="block-reason" label={t('blocks.reason')} error={errors.reason?.message}>
                <Input id="block-reason" aria-invalid={errors.reason !== undefined} {...register('reason')} />
            </FormField>
            <FormField id="block-area" label={t('blocks.area')} error={errors.field_area_id?.message}>
                <NativeSelect id="block-area" {...register('field_area_id')}>
                    <option value="">{t('blocks.wholeField')}</option>
                    {reference.field_areas
                        .filter((area) => area.kind === 'half')
                        .map((area) => (
                            <option key={area.id} value={area.id}>
                                {area.name}
                            </option>
                        ))}
                </NativeSelect>
            </FormField>
            <div className="grid grid-cols-[1fr_7rem] gap-4">
                <FormField id="block-start" label={t('blocks.startsAt')} error={errors.start_date?.message}>
                    <DateTextInput id="block-start" aria-invalid={errors.start_date !== undefined} {...register('start_date')} />
                </FormField>
                <FormField id="block-start-time" label={t('blocks.time')} error={errors.start_time?.message}>
                    <QuarterTimeSelect id="block-start-time" step={reference.kickoff_step_min} {...register('start_time')} />
                </FormField>
                <FormField id="block-end" label={t('blocks.endsAt')} error={errors.end_date?.message}>
                    <DateTextInput id="block-end" aria-invalid={errors.end_date !== undefined} {...register('end_date')} />
                </FormField>
                <FormField id="block-end-time" label={t('blocks.time')} error={errors.end_time?.message}>
                    <QuarterTimeSelect id="block-end-time" step={reference.kickoff_step_min} aria-invalid={errors.end_time !== undefined} {...register('end_time')} />
                </FormField>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={save.isPending}>
                    {save.isPending ? t('common.saving') : t('common.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
