import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { ApiError } from '@/api/client';
import type { Category, Team } from '@/api/types';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { QuarterTimeSelect } from '@/components/TimeFields';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useReferenceData } from '@/hooks/useReferenceData';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { weekdayName } from '@/lib/dates';
import { numberOrNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';
import { useSaveTeam } from './useTeams';

const optionalInteger = z.string().refine((v) => v.trim() === '' || /^\d+$/.test(v.trim()), t('common.invalidNumber'));

const schema = z.object({
    name: z.string().trim().min(1, t('common.required')),
    category_id: z.string().min(1, t('common.required')),
    game_format: z.enum(['', 'f7', 'f11']),
    use_color: z.boolean(),
    color: z.string(),
    match_duration_min: optionalInteger,
    post_min: optionalInteger,
    is_active: z.boolean(),
    // Horari preferent a casa: dia (buit = cap), hora i camp (buit = qualsevol camp lliure).
    pref_day: z.string(),
    pref_time: z.string(),
    pref_area: z.string(),
}).refine((v) => v.pref_day === '' || v.pref_time !== '', { message: t('common.required'), path: ['pref_time'] });

type FormValues = z.infer<typeof schema>;

const fields = ['name', 'category_id', 'game_format', 'color', 'match_duration_min', 'post_min', 'is_active', 'pref_day', 'pref_time', 'pref_area'] as const;

/** Dissabte i diumenge primer: són els dies de partit. */
const WEEKDAY_ORDER = [6, 7, 1, 2, 3, 4, 5] as const;

interface Props {
    open: boolean;
    team: Team | null;
    categories: Category[];
    onOpenChange: (open: boolean) => void;
}

export function TeamFormDialog({ open, team, categories, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{team === null ? t('teams.create') : t('teams.edit')}</DialogTitle>
                </DialogHeader>
                {open && <TeamForm team={team} categories={categories} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

function TeamForm({ team, categories, onDone }: { team: Team | null; categories: Category[]; onDone: () => void }) {
    const save = useSaveTeam();
    const [formError, setFormError] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: team?.name ?? '',
            category_id: String(team?.category_id ?? categories[0]?.id ?? ''),
            game_format: team?.game_format ?? '',
            use_color: team?.color !== null && team?.color !== undefined,
            color: team?.color ?? '#0B4EA2',
            match_duration_min: team?.match_duration_min?.toString() ?? '',
            post_min: team?.post_min?.toString() ?? '',
            is_active: team?.is_active ?? true,
            pref_day: team?.preferred_schedule?.weekday?.toString() ?? '',
            pref_time: team?.preferred_schedule?.time ?? '',
            pref_area: team?.preferred_schedule?.field_area_id?.toString() ?? '',
        },
    });

    const categoryId = useWatch({ control, name: 'category_id' });
    const category = categories.find((c) => String(c.id) === categoryId);
    const { data: reference } = useReferenceData();
    const gameFormat = useWatch({ control, name: 'game_format' });
    const prefDay = useWatch({ control, name: 'pref_day' });
    // El format efectiu: el propi de l'equip o el de la seva categoria (si s'està canviant de categoria, la triada).
    const effectiveFormat = gameFormat === '' ? (category?.game_format ?? team?.category.game_format) : gameFormat;
    const areas = (reference?.field_areas ?? []).filter((area) => effectiveFormat !== 'f11' || area.kind === 'full');
    const inherited = team?.inherited_preference ?? null;
    const inheritedText =
        inherited === null || inherited.weekday === null || inherited.time === null
            ? null
            : t('teams.inheritedPreference', {
                  source: t(`teams.inheritedSource.${inherited.source}`),
                  value: `${weekdayName(inherited.weekday)} ${inherited.time}`,
              });
    const useColor = useWatch({ control, name: 'use_color' });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            {
                id: team?.id ?? null,
                input: {
                    name: values.name,
                    category_id: Number(values.category_id),
                    game_format: values.game_format === '' ? null : values.game_format,
                    color: values.use_color ? values.color.toUpperCase() : null,
                    match_duration_min: numberOrNull(values.match_duration_min),
                    post_min: numberOrNull(values.post_min),
                    is_active: values.is_active,
                    preferred_schedule:
                        values.pref_day === '' ? null : { weekday: Number(values.pref_day), time: values.pref_time, field_area_id: numberOrNull(values.pref_area) },
                },
            },
            {
                onSuccess: () => {
                    toast.success(team === null ? t('teams.created') : t('teams.updated'));
                    onDone();
                },
                onError: (error) => {
                    // Els errors del servidor de l'horari preferent surten als camps corresponents.
                    const mapped: Record<string, (typeof fields)[number]> = { 'preferred_schedule.weekday': 'pref_day', 'preferred_schedule.time': 'pref_time', 'preferred_schedule.field_area_id': 'pref_area' };
                    if (error instanceof ApiError) {
                        for (const [key, field] of Object.entries(mapped)) {
                            const message = error.firstError(key);
                            if (message !== undefined) setError(field, { type: 'server', message });
                        }
                    }
                    setFormError(applyServerErrors(error, setError, fields));
                },
            },
        );
    });

    const durationHelp = t('teams.inheritHelp', { value: category?.match_duration_min ?? '—' });
    const postHelp = t('teams.inheritHelp', { value: category?.post_min ?? '—' });

    return (
        <form onSubmit={onSubmit} noValidate className="grid gap-4">
            {formError !== null && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{formError}</AlertDescription>
                </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="team-name" label={t('teams.name')} error={errors.name?.message}>
                    <Input id="team-name" aria-invalid={errors.name !== undefined} {...register('name')} />
                </FormField>
                <FormField id="team-category" label={t('teams.category')} error={errors.category_id?.message}>
                    <NativeSelect id="team-category" {...register('category_id')}>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            </div>
            <FormField id="team-format" label={t('teams.format')} error={errors.game_format?.message}>
                <NativeSelect id="team-format" {...register('game_format')}>
                    <option value="">{t('teams.inherit', { value: category === undefined ? '—' : t(`labels.format.${category.game_format}`) })}</option>
                    <option value="f7">{t('labels.format.f7')}</option>
                    <option value="f11">{t('labels.format.f11')}</option>
                </NativeSelect>
            </FormField>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="team-duration" label={t('teams.duration')} error={errors.match_duration_min?.message} help={durationHelp}>
                    <Input
                        id="team-duration"
                        inputMode="numeric"
                        placeholder={String(category?.match_duration_min ?? '')}
                        aria-describedby={describedBy('team-duration', errors.match_duration_min?.message, durationHelp)}
                        {...register('match_duration_min')}
                    />
                </FormField>
                <FormField id="team-post" label={t('teams.post')} error={errors.post_min?.message} help={postHelp}>
                    <Input
                        id="team-post"
                        inputMode="numeric"
                        placeholder={String(category?.post_min ?? '')}
                        aria-describedby={describedBy('team-post', errors.post_min?.message, postHelp)}
                        {...register('post_min')}
                    />
                </FormField>
            </div>
            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">{t('teams.color')}</legend>
                <div className="flex items-center gap-3">
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" className="size-4 accent-primary" {...register('use_color')} />
                        {t('teams.color')}
                    </label>
                    {useColor && <input type="color" aria-label={t('teams.color')} className="h-9 w-14 cursor-pointer rounded border" {...register('color')} />}
                    {!useColor && <span className="text-sm text-muted-foreground">{t('teams.noColor')}</span>}
                </div>
                {errors.color?.message !== undefined && <p className="text-sm text-destructive">{errors.color.message}</p>}
            </fieldset>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" {...register('is_active')} />
                {t('common.active')}
            </label>
            <fieldset className="grid gap-3 rounded-lg border border-primary/20 bg-secondary/40 p-3">
                <legend className="px-1 text-sm font-semibold text-primary">{t('teams.preferredSchedule')}</legend>
                <p className="text-xs text-muted-foreground">{t('teams.preferredHelp')}</p>
                <div className="grid gap-3 sm:grid-cols-3">
                    <FormField id="team-pref-day" label={t('teams.preferredDay')} error={errors.pref_day?.message}>
                        <NativeSelect id="team-pref-day" {...register('pref_day')}>
                            <option value="">{t('teams.preferredNone')}</option>
                            {WEEKDAY_ORDER.map((day) => (
                                <option key={day} value={day}>
                                    {weekdayName(day)}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField id="team-pref-time" label={t('teams.preferredTime')} error={errors.pref_time?.message}>
                        <QuarterTimeSelect
                            id="team-pref-time"
                            from={reference?.operating_hours.first_kickoff}
                            to={reference?.operating_hours.last_kickoff}
                            step={reference?.kickoff_step_min}
                            emptyLabel={t('common.none')}
                            disabled={prefDay === ''}
                            aria-invalid={errors.pref_time !== undefined}
                            {...register('pref_time')}
                        />
                    </FormField>
                    <FormField id="team-pref-area" label={t('teams.preferredField')} error={errors.pref_area?.message}>
                        <NativeSelect id="team-pref-area" disabled={prefDay === ''} {...register('pref_area')}>
                            <option value="">{t('teams.anyField')}</option>
                            {areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                </div>
                {prefDay === '' && inheritedText !== null && <p className="text-xs text-muted-foreground">{inheritedText}</p>}
            </fieldset>
            <p className="text-xs text-muted-foreground">{t('teams.crestNote')}</p>
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
