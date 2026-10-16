import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { Matchday } from '@/api/types';
import { DateTextInput } from '@/components/DateTextInput';
import { FormField } from '@/components/FormField';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { displayToIso, isSameOrAfter, requiredDisplayDate } from '@/lib/dateSchema';
import { addDays, isoWeekday, parseDisplayDate, toDisplayDate } from '@/lib/dates';
import { describedBy } from '@/lib/a11y';
import { emptyToNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';
import { useSaveMatchday } from './useSeasons';

const schema = z
    .object({
        starts_on: requiredDisplayDate,
        ends_on: requiredDisplayDate,
        label: z.string(),
    })
    .refine((v) => isSameOrAfter(v.starts_on, v.ends_on), { message: t('common.endBeforeStart'), path: ['ends_on'] });

type FormValues = z.infer<typeof schema>;

/** Proper dissabte a partir d'avui. */
function nextSaturday(): string {
    const today = new Date().toISOString().slice(0, 10);
    const offset = (6 - isoWeekday(today) + 7) % 7;

    return addDays(today, offset);
}

interface Props {
    open: boolean;
    seasonId: number;
    matchday: Matchday | null;
    onOpenChange: (open: boolean) => void;
}

export function MatchdayFormDialog({ open, seasonId, matchday, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{matchday === null ? t('seasons.createMatchday') : t('seasons.editMatchday')}</DialogTitle>
                </DialogHeader>
                {open && <MatchdayForm seasonId={seasonId} matchday={matchday} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

function MatchdayForm({ seasonId, matchday, onDone }: { seasonId: number; matchday: Matchday | null; onDone: () => void }) {
    const save = useSaveMatchday(seasonId);
    const [formError, setFormError] = useState<string | null>(null);
    const saturday = nextSaturday();
    const {
        register,
        handleSubmit,
        setError,
        setValue,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: {
            starts_on: toDisplayDate(matchday?.starts_on ?? saturday),
            ends_on: toDisplayDate(matchday?.ends_on ?? addDays(saturday, 1)),
            label: matchday?.label ?? '',
        },
    });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            {
                id: matchday?.id ?? null,
                input: { starts_on: displayToIso(values.starts_on) ?? '', ends_on: displayToIso(values.ends_on) ?? '', label: emptyToNull(values.label) },
            },
            {
                onSuccess: () => {
                    toast.success(t('seasons.matchdayCreated'));
                    onDone();
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, ['starts_on', 'ends_on', 'label'] as const)),
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
                <FormField id="matchday-start" label={t('seasons.firstDay')} error={errors.starts_on?.message}>
                    <DateTextInput
                        id="matchday-start"
                        aria-invalid={errors.starts_on !== undefined}
                        {...register('starts_on', {
                            // En triar el primer dia, l'últim passa a ser l'endemà.
                            onChange: (e: { target: { value: string } }) => {
                                const iso = parseDisplayDate(e.target.value);
                                if (iso !== null) setValue('ends_on', toDisplayDate(addDays(iso, 1)));
                            },
                        })}
                    />
                </FormField>
                <FormField id="matchday-end" label={t('seasons.lastDay')} error={errors.ends_on?.message}>
                    <DateTextInput id="matchday-end" aria-invalid={errors.ends_on !== undefined} {...register('ends_on')} />
                </FormField>
            </div>
            <FormField id="matchday-label" label={t('seasons.matchdayLabel')} error={errors.label?.message} help={t('seasons.matchdayLabelHelp')}>
                <Input id="matchday-label" aria-describedby={describedBy('matchday-label', errors.label?.message, t('seasons.matchdayLabelHelp'))} {...register('label')} />
            </FormField>
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
