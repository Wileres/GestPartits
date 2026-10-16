import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { AvailabilitySlot, ReferenceData } from '@/api/types';
import { DateTextInput } from '@/components/DateTextInput';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { QuarterTimeSelect } from '@/components/TimeFields';
import { Input } from '@/components/ui/input';
import { ca, t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { displayToIso, optionalDisplayDate } from '@/lib/dateSchema';
import { toDisplayDate } from '@/lib/dates';
import { numberOrNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';
import { isOnGrid, isTime, toMinutes } from '@/lib/time';
import { useSaveSlot } from './useSlots';

function buildSchema(step: number) {
    const time = z
        .string()
        .refine(isTime, t('common.invalidTime'))
        .refine((v) => !isTime(v) || isOnGrid(v, step), t('common.quarterHour'));

    return z
        .object({
            kind: z.enum(['weekly', 'date']),
            weekday: z.string(),
            specific_date: optionalDisplayDate,
            label: z.string().trim().min(1, t('common.required')),
            opens_at: time,
            last_kickoff_at: time,
            default_fill_order: z.string().refine((v) => v === '' || /^\d+$/.test(v), t('common.invalidNumber')),
            is_active: z.boolean(),
        })
        .superRefine((v, ctx) => {
            if (v.kind === 'date' && v.specific_date === '') ctx.addIssue({ code: 'custom', path: ['specific_date'], message: t('common.required') });
            if (isTime(v.opens_at) && isTime(v.last_kickoff_at) && toMinutes(v.last_kickoff_at) < toMinutes(v.opens_at)) {
                ctx.addIssue({ code: 'custom', path: ['last_kickoff_at'], message: t('common.endBeforeStart') });
            }
        });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface Props {
    open: boolean;
    slot: AvailabilitySlot | null;
    defaultKind: 'weekly' | 'date';
    reference: ReferenceData;
    onOpenChange: (open: boolean) => void;
}

export function SlotFormDialog({ open, slot, defaultKind, reference, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{slot === null ? (defaultKind === 'date' ? t('slots.createException') : t('slots.create')) : t('slots.edit')}</DialogTitle>
                </DialogHeader>
                {open && <SlotForm slot={slot} defaultKind={defaultKind} step={reference.kickoff_step_min} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

function SlotForm({ slot, defaultKind, step, onDone }: { slot: AvailabilitySlot | null; defaultKind: 'weekly' | 'date'; step: number; onDone: () => void }) {
    const save = useSaveSlot();
    const [formError, setFormError] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(buildSchema(step)),
        defaultValues: {
            kind: slot === null ? defaultKind : slot.specific_date !== null ? 'date' : 'weekly',
            weekday: String(slot?.weekday ?? 6),
            specific_date: toDisplayDate(slot?.specific_date),
            label: slot?.label ?? '',
            opens_at: slot?.opens_at ?? '09:00',
            last_kickoff_at: slot?.last_kickoff_at ?? '13:00',
            default_fill_order: slot?.default_fill_order?.toString() ?? '',
            is_active: slot?.is_active ?? true,
        },
    });

    const kind = useWatch({ control, name: 'kind' });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            {
                id: slot?.id ?? null,
                input: {
                    weekday: values.kind === 'weekly' ? Number(values.weekday) : null,
                    specific_date: values.kind === 'date' ? displayToIso(values.specific_date) : null,
                    label: values.label,
                    opens_at: values.opens_at,
                    last_kickoff_at: values.last_kickoff_at,
                    default_fill_order: numberOrNull(values.default_fill_order),
                    is_active: values.is_active,
                },
            },
            {
                onSuccess: () => {
                    toast.success(t('slots.saved'));
                    onDone();
                },
                onError: (error) =>
                    setFormError(applyServerErrors(error, setError, ['weekday', 'specific_date', 'label', 'opens_at', 'last_kickoff_at', 'default_fill_order', 'is_active'] as const)),
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
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="slot-kind" label={t('slots.kind')}>
                    <NativeSelect id="slot-kind" {...register('kind')}>
                        <option value="weekly">{t('slots.kindWeekly')}</option>
                        <option value="date">{t('slots.kindDate')}</option>
                    </NativeSelect>
                </FormField>
                {kind === 'weekly' ? (
                    <FormField id="slot-weekday" label={t('slots.weekday')} error={errors.weekday?.message}>
                        <NativeSelect id="slot-weekday" {...register('weekday')}>
                            {ca.weekdays.map((day, index) => (
                                <option key={day} value={index + 1}>
                                    {day}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                ) : (
                    <FormField id="slot-date" label={t('slots.date')} error={errors.specific_date?.message}>
                        <DateTextInput id="slot-date" aria-invalid={errors.specific_date !== undefined} {...register('specific_date')} />
                    </FormField>
                )}
            </div>
            <FormField id="slot-label" label={t('slots.label')} error={errors.label?.message}>
                <Input id="slot-label" aria-invalid={errors.label !== undefined} {...register('label')} />
            </FormField>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="slot-opens" label={t('slots.opensAt')} error={errors.opens_at?.message}>
                    <QuarterTimeSelect id="slot-opens" step={step} aria-invalid={errors.opens_at !== undefined} {...register('opens_at')} />
                </FormField>
                <FormField id="slot-last" label={t('slots.lastKickoffAt')} error={errors.last_kickoff_at?.message}>
                    <QuarterTimeSelect id="slot-last" step={step} aria-invalid={errors.last_kickoff_at !== undefined} {...register('last_kickoff_at')} />
                </FormField>
            </div>
            <FormField id="slot-fill" label={t('slots.fillOrder')} error={errors.default_fill_order?.message} help={t('slots.fillOrderHelp')}>
                <Input
                    id="slot-fill"
                    inputMode="numeric"
                    aria-describedby={describedBy('slot-fill', errors.default_fill_order?.message, t('slots.fillOrderHelp'))}
                    {...register('default_fill_order')}
                />
            </FormField>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" {...register('is_active')} />
                {t('slots.active')}
            </label>
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
