import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { Category, ReferenceData, SchedulingRule, Team } from '@/api/types';
import { DateTextInput } from '@/components/DateTextInput';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { QuarterTimeSelect } from '@/components/TimeFields';
import { Input } from '@/components/ui/input';
import { useSaveRule } from '@/features/slots/useSlots';
import { ca, t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { displayToIso, isSameOrAfter, optionalDisplayDate } from '@/lib/dateSchema';
import { toDisplayDate } from '@/lib/dates';
import { emptyToNull, numberOrNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';
import { isOnGrid, isTime, toMinutes } from '@/lib/time';

function buildSchema(step: number) {
    const optionalTime = z
        .string()
        .refine((v) => v === '' || isTime(v), t('common.invalidTime'))
        .refine((v) => v === '' || !isTime(v) || isOnGrid(v, step), t('common.quarterHour'));

    return z
        .object({
            scope: z.enum(['team', 'category', 'format']),
            team_id: z.string(),
            category_id: z.string(),
            game_format: z.enum(['f7', 'f11']),
            weekday: z.string(),
            preferred_time: optionalTime,
            not_before: optionalTime,
            not_after: optionalTime,
            preferred_field_area_id: z.string(),
            priority: z.string().regex(/^-?\d+$/, t('common.invalidNumber')),
            strength: z.enum(['mandatory', 'recommended']),
            valid_from: optionalDisplayDate,
            valid_to: optionalDisplayDate,
            is_active: z.boolean(),
            notes: z.string(),
        })
        .superRefine((v, ctx) => {
            if (v.scope === 'team' && v.team_id === '') ctx.addIssue({ code: 'custom', path: ['team_id'], message: t('common.required') });
            if (v.scope === 'category' && v.category_id === '') ctx.addIssue({ code: 'custom', path: ['category_id'], message: t('common.required') });
            if (v.preferred_time !== '' && v.weekday === '') ctx.addIssue({ code: 'custom', path: ['weekday'], message: t('common.required') });
            if (v.preferred_time === '' && v.not_before === '' && v.not_after === '') {
                ctx.addIssue({ code: 'custom', path: ['preferred_time'], message: t('common.required') });
            }
            if (isTime(v.not_before) && isTime(v.not_after) && toMinutes(v.not_after) < toMinutes(v.not_before)) {
                ctx.addIssue({ code: 'custom', path: ['not_after'], message: t('common.endBeforeStart') });
            }
            if (!isSameOrAfter(v.valid_from, v.valid_to)) {
                ctx.addIssue({ code: 'custom', path: ['valid_to'], message: t('common.endBeforeStart') });
            }
        });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

const fields = [
    'team_id', 'category_id', 'game_format', 'weekday', 'preferred_time', 'not_before', 'not_after',
    'preferred_field_area_id', 'priority', 'strength', 'valid_from', 'valid_to', 'is_active', 'notes',
] as const;

interface Props {
    open: boolean;
    rule: SchedulingRule | null;
    teams: Team[];
    categories: Category[];
    reference: ReferenceData;
    onOpenChange: (open: boolean) => void;
}

export function RuleFormDialog({ open, rule, teams, categories, reference, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{rule === null ? t('rules.create') : t('rules.edit')}</DialogTitle>
                </DialogHeader>
                {open && <RuleForm rule={rule} teams={teams} categories={categories} reference={reference} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

function RuleForm({ rule, teams, categories, reference, onDone }: Omit<Props, 'open' | 'onOpenChange'> & { onDone: () => void }) {
    const save = useSaveRule();
    const [formError, setFormError] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(buildSchema(reference.kickoff_step_min)),
        defaultValues: {
            scope: rule === null || rule.team_id !== null ? 'team' : rule.category_id !== null ? 'category' : 'format',
            team_id: rule?.team_id?.toString() ?? '',
            category_id: rule?.category_id?.toString() ?? '',
            game_format: rule?.game_format ?? 'f7',
            weekday: rule?.weekday?.toString() ?? '6',
            preferred_time: rule?.preferred_time ?? '',
            not_before: rule?.not_before ?? '',
            not_after: rule?.not_after ?? '',
            preferred_field_area_id: rule?.preferred_field_area_id?.toString() ?? '',
            priority: String(rule?.priority ?? 50),
            strength: rule?.strength ?? 'recommended',
            valid_from: toDisplayDate(rule?.valid_from),
            valid_to: toDisplayDate(rule?.valid_to),
            is_active: rule?.is_active ?? true,
            notes: rule?.notes ?? '',
        },
    });

    const scope = useWatch({ control, name: 'scope' });

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            {
                id: rule?.id ?? null,
                input: {
                    team_id: values.scope === 'team' ? numberOrNull(values.team_id) : null,
                    category_id: values.scope === 'category' ? numberOrNull(values.category_id) : null,
                    game_format: values.scope === 'format' ? values.game_format : null,
                    weekday: values.preferred_time === '' ? numberOrNull(values.weekday) : Number(values.weekday),
                    preferred_time: emptyToNull(values.preferred_time),
                    not_before: emptyToNull(values.not_before),
                    not_after: emptyToNull(values.not_after),
                    preferred_field_area_id: values.preferred_time === '' ? null : numberOrNull(values.preferred_field_area_id),
                    priority: Number(values.priority),
                    strength: values.strength,
                    valid_from: displayToIso(values.valid_from),
                    valid_to: displayToIso(values.valid_to),
                    is_active: values.is_active,
                    notes: emptyToNull(values.notes),
                },
            },
            {
                onSuccess: () => {
                    toast.success(t('rules.saved'));
                    onDone();
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, fields)),
            },
        );
    });

    const timeField = (name: 'preferred_time' | 'not_before' | 'not_after', label: string) => (
        <FormField id={`rule-${name}`} label={label} error={errors[name]?.message}>
            <QuarterTimeSelect id={`rule-${name}`} step={reference.kickoff_step_min} emptyLabel={t('common.none')} aria-invalid={errors[name] !== undefined} {...register(name)} />
        </FormField>
    );

    return (
        <form onSubmit={onSubmit} noValidate className="grid gap-4">
            {formError !== null && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{formError}</AlertDescription>
                </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="rule-scope" label={t('rules.scopeType')}>
                    <NativeSelect id="rule-scope" {...register('scope')}>
                        <option value="team">{t('rules.scopeTeam')}</option>
                        <option value="category">{t('rules.scopeCategory')}</option>
                        <option value="format">{t('rules.scopeFormat')}</option>
                    </NativeSelect>
                </FormField>
                {scope === 'team' && (
                    <FormField id="rule-team" label={t('rules.team')} error={errors.team_id?.message}>
                        <NativeSelect id="rule-team" aria-invalid={errors.team_id !== undefined} {...register('team_id')}>
                            <option value="">{t('common.none')}</option>
                            {teams.map((team) => (
                                <option key={team.id} value={team.id}>
                                    {team.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                )}
                {scope === 'category' && (
                    <FormField id="rule-category" label={t('rules.category')} error={errors.category_id?.message}>
                        <NativeSelect id="rule-category" aria-invalid={errors.category_id !== undefined} {...register('category_id')}>
                            <option value="">{t('common.none')}</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                )}
                {scope === 'format' && (
                    <FormField id="rule-format" label={t('rules.format')} error={errors.game_format?.message}>
                        <NativeSelect id="rule-format" {...register('game_format')}>
                            <option value="f7">{t('labels.format.f7')}</option>
                            <option value="f11">{t('labels.format.f11')}</option>
                        </NativeSelect>
                    </FormField>
                )}
            </div>

            <fieldset className="grid gap-4 rounded-lg border p-3">
                <legend className="px-1 text-sm font-medium">{t('rules.preference')}</legend>
                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField id="rule-weekday" label={t('rules.weekday')} error={errors.weekday?.message}>
                        <NativeSelect id="rule-weekday" {...register('weekday')}>
                            {ca.weekdays.map((day, index) => (
                                <option key={day} value={index + 1}>
                                    {day}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    {timeField('preferred_time', t('rules.preferredTime'))}
                    <FormField id="rule-area" label={t('rules.area')} error={errors.preferred_field_area_id?.message}>
                        <NativeSelect id="rule-area" {...register('preferred_field_area_id')}>
                            <option value="">{t('rules.anyArea')}</option>
                            {reference.field_areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                </div>
            </fieldset>

            <fieldset className="grid gap-4 rounded-lg border p-3">
                <legend className="px-1 text-sm font-medium">{t('rules.window')}</legend>
                <div className="grid gap-4 sm:grid-cols-2">
                    {timeField('not_before', t('rules.notBefore'))}
                    {timeField('not_after', t('rules.notAfter'))}
                </div>
            </fieldset>

            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="rule-strength" label={t('rules.strength')} error={errors.strength?.message}>
                    <NativeSelect id="rule-strength" {...register('strength')}>
                        <option value="recommended">{t('labels.strength.recommended')}</option>
                        <option value="mandatory">{t('labels.strength.mandatory')}</option>
                    </NativeSelect>
                </FormField>
                <FormField id="rule-priority" label={t('rules.priority')} error={errors.priority?.message} help={t('rules.priorityHelp')}>
                    <Input id="rule-priority" inputMode="numeric" aria-describedby={describedBy('rule-priority', errors.priority?.message, t('rules.priorityHelp'))} {...register('priority')} />
                </FormField>
                <FormField id="rule-from" label={t('rules.validFrom')} error={errors.valid_from?.message}>
                    <DateTextInput id="rule-from" aria-invalid={errors.valid_from !== undefined} {...register('valid_from')} />
                </FormField>
                <FormField id="rule-to" label={t('rules.validTo')} error={errors.valid_to?.message}>
                    <DateTextInput id="rule-to" aria-invalid={errors.valid_to !== undefined} {...register('valid_to')} />
                </FormField>
            </div>
            <FormField id="rule-notes" label={t('rules.notes')} error={errors.notes?.message}>
                <Input id="rule-notes" {...register('notes')} />
            </FormField>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" {...register('is_active')} />
                {t('rules.active')}
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
