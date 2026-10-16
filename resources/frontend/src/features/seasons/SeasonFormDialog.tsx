import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { Season } from '@/api/types';
import { DateTextInput } from '@/components/DateTextInput';
import { FormField } from '@/components/FormField';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { requiredDisplayDate, displayToIso, isSameOrAfter } from '@/lib/dateSchema';
import { toDisplayDate } from '@/lib/dates';
import { applyServerErrors } from '@/lib/forms';
import { useSaveSeason } from './useSeasons';

const schema = z
    .object({
        name: z.string().regex(/^\d{4}-\d{4}$/, t('seasons.nameHelp')),
        starts_on: requiredDisplayDate,
        ends_on: requiredDisplayDate,
        is_current: z.boolean(),
    })
    .refine((v) => isSameOrAfter(v.starts_on, v.ends_on) && v.starts_on !== v.ends_on, { message: t('common.endBeforeStart'), path: ['ends_on'] });

type FormValues = z.infer<typeof schema>;

function nextSeasonDefaults(): FormValues {
    const year = new Date().getMonth() >= 6 ? new Date().getFullYear() : new Date().getFullYear() - 1;

    return { name: `${year}-${year + 1}`, starts_on: `01/09/${year}`, ends_on: `30/06/${year + 1}`, is_current: true };
}

export function SeasonFormDialog({ open, season, onOpenChange }: { open: boolean; season: Season | null; onOpenChange: (open: boolean) => void }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{season === null ? t('seasons.create') : t('seasons.edit')}</DialogTitle>
                </DialogHeader>
                {open && <SeasonForm season={season} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

function SeasonForm({ season, onDone }: { season: Season | null; onDone: () => void }) {
    const save = useSaveSeason();
    const [formError, setFormError] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues:
            season === null
                ? nextSeasonDefaults()
                : { name: season.name, starts_on: toDisplayDate(season.starts_on), ends_on: toDisplayDate(season.ends_on), is_current: season.is_current },
    });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            { id: season?.id ?? null, input: { ...values, starts_on: displayToIso(values.starts_on) ?? '', ends_on: displayToIso(values.ends_on) ?? '' } },
            {
                onSuccess: () => {
                    toast.success(t('seasons.created'));
                    onDone();
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, ['name', 'starts_on', 'ends_on', 'is_current'] as const)),
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
            <FormField id="season-name" label={t('seasons.name')} error={errors.name?.message} help={t('seasons.nameHelp')}>
                <Input id="season-name" aria-invalid={errors.name !== undefined} {...register('name')} />
            </FormField>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="season-start" label={t('seasons.startsOn')} error={errors.starts_on?.message}>
                    <DateTextInput id="season-start" aria-invalid={errors.starts_on !== undefined} {...register('starts_on')} />
                </FormField>
                <FormField id="season-end" label={t('seasons.endsOn')} error={errors.ends_on?.message}>
                    <DateTextInput id="season-end" aria-invalid={errors.ends_on !== undefined} {...register('ends_on')} />
                </FormField>
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" {...register('is_current')} />
                {t('seasons.current')}
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
