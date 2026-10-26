import { zodResolver } from '@hookform/resolvers/zod';
import { Bus, House, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { ApiError } from '@/api/client';
import type { MatchInput } from '@/api/matches';
import type { FieldArea, GameFormat, HomeAway, Match, Matchday, ReferenceData, Team } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { QuarterTimeSelect, TimeTextInput } from '@/components/TimeFields';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { formatWeekdayDate, matchdayDates } from '@/lib/dates';
import { emptyToNull, errorMessage, numberOrNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';
import { describePreference } from '@/lib/rules';
import { fromMinutes, isOnGrid, isTime, isWithin, toMinutes } from '@/lib/time';
import { useDeleteMatch, useSaveMatch } from './useMatches';

const statuses = ['proposed', 'confirmed', 'cancelled'] as const;
const optionalInteger = z.string().refine((v) => v.trim() === '' || /^\d+$/.test(v.trim()), t('common.invalidNumber'));
const scoreField = z.string().refine((v) => v.trim() === '' || (/^\d{1,2}$/.test(v.trim())), t('matches.scoreInvalid'));
const optionalTime = z.string().refine((v) => v === '' || isTime(v), t('common.invalidTime'));

function buildSchema(reference: ReferenceData) {
    const { first_kickoff: from, last_kickoff: to } = reference.operating_hours;

    return z
        .object({
            status: z.enum(statuses),
            home_away: z.enum(['', 'home', 'away']),
            opponent: z.string(),
            round_label: z.string(),
            game_format: z.enum(['', 'f7', 'f11']),
            match_date: z.string(),
            kickoff_time: optionalTime,
            call_time: optionalTime,
            duration_min: optionalInteger,
            post_min: optionalInteger,
            field_area_id: z.string(),
            venue_name: z.string(),
            address: z.string(),
            municipality: z.string(),
            maps_url: z.string().refine((v) => v.trim() === '' || /^https:\/\/\S+$/.test(v.trim()), t('common.invalidUrl')),
            is_locked: z.boolean(),
            rule_override_reason: z.string(),
            notes: z.string(),
            whatsapp_extra: z.string(),
            show_in_image: z.boolean(),
            score_team: scoreField,
            score_opponent: scoreField,
        })
        .superRefine((v, ctx) => {
            if (v.home_away === '') {
                ctx.addIssue({ code: 'custom', path: ['home_away'], message: t('common.required') });
            }
            if ((v.score_team.trim() === '') !== (v.score_opponent.trim() === '')) {
                ctx.addIssue({ code: 'custom', path: [v.score_team.trim() === '' ? 'score_team' : 'score_opponent'], message: t('matches.scoreBoth') });
            }
            if (v.status === 'cancelled' && (v.score_team.trim() !== '' || v.score_opponent.trim() !== '')) {
                ctx.addIssue({ code: 'custom', path: ['score_team'], message: t('matches.resultCancelled') });
            }
            if (v.kickoff_time !== '' && v.match_date === '') {
                ctx.addIssue({ code: 'custom', path: ['match_date'], message: t('common.required') });
            }
            if (v.home_away === 'home' && v.kickoff_time !== '') {
                if (!isTime(v.kickoff_time)) {
                    ctx.addIssue({ code: 'custom', path: ['kickoff_time'], message: t('common.invalidTime') });
                } else if (!isOnGrid(v.kickoff_time, reference.kickoff_step_min)) {
                    ctx.addIssue({ code: 'custom', path: ['kickoff_time'], message: t('common.quarterHour') });
                } else if (!isWithin(v.kickoff_time, from, to)) {
                    ctx.addIssue({ code: 'custom', path: ['kickoff_time'], message: t('common.operatingHours', { from, to }) });
                }
                if (v.field_area_id === '') {
                    ctx.addIssue({ code: 'custom', path: ['field_area_id'], message: t('common.required') });
                }
            }
            if (isTime(v.kickoff_time) && isTime(v.call_time) && toMinutes(v.call_time) > toMinutes(v.kickoff_time)) {
                ctx.addIssue({ code: 'custom', path: ['call_time'], message: t('common.callAfterKickoff') });
            }
        });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

const fields = [
    'status', 'home_away', 'opponent', 'round_label', 'game_format', 'match_date', 'kickoff_time', 'call_time', 'duration_min', 'post_min',
    'field_area_id', 'venue_name', 'address', 'municipality', 'maps_url', 'is_locked', 'rule_override_reason', 'notes', 'whatsapp_extra', 'show_in_image', 'score_team', 'score_opponent',
] as const;

export interface MatchSheetTarget {
    team: Team;
    match: Match | null;
    /** Condició suggerida per a un partit nou (el contrari de l'últim partit de l'equip). */
    suggestedHomeAway?: HomeAway | null;
}

interface MatchSheetProps {
    target: MatchSheetTarget | null;
    matchday: Matchday;
    reference: ReferenceData;
    onClose: () => void;
}

export function MatchSheet({ target, matchday, reference, onClose }: MatchSheetProps) {
    return (
        <Sheet open={target !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                {target !== null && (
                    <>
                        <SheetHeader>
                            <SheetTitle>
                                {target.match === null ? t('matches.newMatch') : t('matches.edit')} · {target.team.name}
                            </SheetTitle>
                            <SheetDescription>{matchday.display_label}</SheetDescription>
                        </SheetHeader>
                        <MatchForm key={`${target.team.id}-${target.match?.id ?? 'nou'}-${target.match?.lock_version ?? 0}`} {...target} matchday={matchday} reference={reference} onDone={onClose} />
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

function areasFor(format: GameFormat, areas: FieldArea[]): FieldArea[] {
    return format === 'f11' ? areas.filter((a) => a.kind === 'full') : areas;
}

interface MatchFormProps extends MatchSheetTarget {
    matchday: Matchday;
    reference: ReferenceData;
    onDone: () => void;
}

function MatchForm({ team, match, suggestedHomeAway = null, matchday, reference, onDone }: MatchFormProps) {
    const save = useSaveMatch(matchday.id);
    const remove = useDeleteMatch(matchday.id);
    const [formError, setFormError] = useState<string | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const {
        register,
        handleSubmit,
        setError,
        setValue,
        getValues,
        control,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(buildSchema(reference)),
        defaultValues: {
            status: match?.status ?? 'confirmed',
            home_away: match?.home_away ?? suggestedHomeAway ?? '',
            opponent: match?.opponent ?? '',
            round_label: match?.round_label ?? '',
            game_format: match?.game_format ?? '',
            match_date: match?.match_date ?? '',
            kickoff_time: match?.kickoff_time ?? '',
            call_time: match?.call_time ?? '',
            duration_min: String(match?.duration_min ?? team.effective.match_duration_min),
            post_min: String(match?.post_min ?? team.effective.post_min),
            field_area_id: match?.field_area_id?.toString() ?? '',
            venue_name: match?.venue_name ?? '',
            address: match?.address ?? '',
            municipality: match?.municipality ?? '',
            maps_url: match?.maps_url ?? '',
            is_locked: match?.is_locked ?? false,
            rule_override_reason: match?.rule_override_reason ?? '',
            notes: match?.notes ?? '',
            whatsapp_extra: match?.whatsapp_extra ?? '',
            show_in_image: match?.show_in_image ?? true,
            score_team: match?.score_team?.toString() ?? '',
            score_opponent: match?.score_opponent?.toString() ?? '',
        },
    });

    const homeAway = useWatch({ control, name: 'home_away' });
    const gameFormat = useWatch({ control, name: 'game_format' });
    const format: GameFormat = gameFormat === '' ? team.effective.game_format : gameFormat;
    const kickoff = useWatch({ control, name: 'kickoff_time' });
    const duration = Number(useWatch({ control, name: 'duration_min' }));
    const post = Number(useWatch({ control, name: 'post_min' }));
    const isHome = homeAway === 'home';
    const isAway = homeAway === 'away';
    const preference = team.preferred_schedule ?? team.inherited_preference;
    const preferredHint =
        match === null && isHome && kickoff === '' && preference !== null && preference.time !== null
            ? t('matches.preferredLoaded', { value: describePreference(preference, reference.field_areas) })
            : null;
    const occupationEnd = isHome && isTime(kickoff) && Number.isFinite(duration + post) ? fromMinutes(toMinutes(kickoff) + duration + post) : null;

    // Convocatòria per defecte (una hora abans del partit, a casa i a fora): es proposa quan no n'hi ha cap
    // i segueix els canvis d'hora mentre sigui la per defecte; una convocatòria posada a mà es respecta.
    const followCall = (event: { target: { value: string } }) => {
        const time = event.target.value;
        if (!isTime(time)) return;
        const call = getValues('call_time');
        const previousDefault = isTime(kickoff) ? fromMinutes(Math.max(0, toMinutes(kickoff) - 60)) : null;
        if (call === '' || call === previousDefault) {
            setValue('call_time', fromMinutes(Math.max(0, toMinutes(time) - 60)), { shouldDirty: true });
        }
    };

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        const input: MatchInput = {
            team_id: team.id,
            status: values.status,
            home_away: values.home_away === '' ? null : values.home_away,
            opponent: emptyToNull(values.opponent),
            round_label: emptyToNull(values.round_label),
            game_format: values.game_format === '' ? null : values.game_format,
            match_date: emptyToNull(values.match_date),
            kickoff_time: emptyToNull(values.kickoff_time),
            call_time: emptyToNull(values.call_time),
            duration_min: numberOrNull(values.duration_min),
            post_min: numberOrNull(values.post_min),
            field_area_id: values.home_away === 'home' ? numberOrNull(values.field_area_id) : null,
            venue_name: values.home_away === 'away' ? emptyToNull(values.venue_name) : null,
            address: values.home_away === 'away' ? emptyToNull(values.address) : null,
            municipality: values.home_away === 'away' ? emptyToNull(values.municipality) : null,
            maps_url: values.home_away === 'away' ? emptyToNull(values.maps_url) : null,
            is_locked: values.is_locked,
            rule_override_reason: emptyToNull(values.rule_override_reason),
            notes: emptyToNull(values.notes),
            whatsapp_extra: emptyToNull(values.whatsapp_extra),
            show_in_image: values.show_in_image,
            score_team: numberOrNull(values.score_team),
            score_opponent: numberOrNull(values.score_opponent),
        };

        save.mutate(
            { id: match?.id ?? null, lockVersion: match?.lock_version ?? 0, input },
            {
                onSuccess: () => {
                    toast.success(t('matches.updated'));
                    onDone();
                },
                onError: (error) => {
                    if (error instanceof ApiError && error.status === 409) {
                        toast.error(t('common.conflict'));
                        onDone();

                        return;
                    }
                    setFormError(applyServerErrors(error, setError, fields));
                },
            },
        );
    });

    const err = (name: (typeof fields)[number]) => errors[name]?.message;

    return (
        <form onSubmit={onSubmit} noValidate className="grid gap-4 px-4 pb-4">
            {formError !== null && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{formError}</AlertDescription>
                </Alert>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="match-status" label={t('matches.status')} error={err('status')}>
                    <NativeSelect id="match-status" {...register('status')}>
                        {statuses.map((value) => (
                            <option key={value} value={value}>
                                {t(`labels.status.${value}`)}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <fieldset className="grid gap-1.5">
                    <legend className="mb-1.5 text-sm font-medium">{t('matches.condition')}</legend>
                    <div className="grid grid-cols-2 gap-2">
                        {(['home', 'away'] as const).map((value) => (
                            <label
                                key={value}
                                className="flex cursor-pointer items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-secondary has-checked:text-primary"
                            >
                                <input type="radio" value={value} className="sr-only" {...register('home_away')} />
                                {value === 'home' ? <House className="size-4" aria-hidden /> : <Bus className="size-4" aria-hidden />}
                                {t(`labels.homeAwayShort.${value}`)}
                            </label>
                        ))}
                    </div>
                    {err('home_away') !== undefined && (
                        <p role="alert" className="text-sm text-destructive">
                            {err('home_away')}
                        </p>
                    )}
                </fieldset>
            </div>

            <>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="match-opponent" label={t('matches.opponent')} error={err('opponent')}>
                        <Input id="match-opponent" {...register('opponent')} />
                    </FormField>
                    <FormField id="match-round" label={t('matches.round')} error={err('round_label')} help={t('common.optional')}>
                        <Input id="match-round" aria-describedby={describedBy('match-round', err('round_label'), t('common.optional'))} {...register('round_label')} />
                    </FormField>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField id="match-date" label={t('matches.date')} error={err('match_date')}>
                        <NativeSelect id="match-date" aria-invalid={err('match_date') !== undefined} {...register('match_date')}>
                            <option value="">{t('matches.unscheduled')}</option>
                            {matchdayDates(matchday).map((date) => (
                                <option key={date} value={date}>
                                    {formatWeekdayDate(date)}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField id="match-kickoff" label={t('matches.kickoff')} error={err('kickoff_time')}>
                        {isHome ? (
                            <QuarterTimeSelect
                                id="match-kickoff"
                                from={reference.operating_hours.first_kickoff}
                                to={reference.operating_hours.last_kickoff}
                                step={reference.kickoff_step_min}
                                emptyLabel={t('matches.unscheduled')}
                                aria-invalid={err('kickoff_time') !== undefined}
                                aria-describedby={describedBy('match-kickoff', err('kickoff_time'))}
                                {...register('kickoff_time', { onChange: followCall })}
                            />
                        ) : (
                            <TimeTextInput
                                id="match-kickoff"
                                aria-invalid={err('kickoff_time') !== undefined}
                                aria-describedby={describedBy('match-kickoff', err('kickoff_time'))}
                                {...register('kickoff_time', { onChange: followCall })}
                            />
                        )}
                    </FormField>
                    <FormField id="match-call" label={t('matches.call')} error={err('call_time')} help={isHome ? t('matches.callDefault') : undefined}>
                        <TimeTextInput
                            id="match-call"
                            aria-invalid={err('call_time') !== undefined}
                            aria-describedby={describedBy('match-call', err('call_time'), isHome ? t('matches.callDefault') : undefined)}
                            {...register('call_time')}
                        />
                    </FormField>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField id="match-format" label={t('matches.format')} error={err('game_format')}>
                        <NativeSelect id="match-format" {...register('game_format')}>
                            <option value="">
                                {t('matches.defaultFormat')} ({t(`labels.format.${team.effective.game_format}`)})
                            </option>
                            <option value="f7">{t('labels.format.f7')}</option>
                            <option value="f11">{t('labels.format.f11')}</option>
                        </NativeSelect>
                    </FormField>
                    <FormField id="match-duration" label={t('matches.duration')} error={err('duration_min')}>
                        <Input id="match-duration" inputMode="numeric" {...register('duration_min')} />
                    </FormField>
                    <FormField id="match-post" label={t('matches.post')} error={err('post_min')}>
                        <Input id="match-post" inputMode="numeric" {...register('post_min')} />
                    </FormField>
                </div>
            </>

            {isHome && (
                <section className="grid gap-4 rounded-lg border border-primary/20 bg-secondary/40 p-3" aria-labelledby="match-home-title">
                    <div>
                        <h3 id="match-home-title" className="flex items-center gap-2 text-sm font-semibold text-primary">
                            <House className="size-4" aria-hidden />
                            {t('matches.homeSection')}
                        </h3>
                        <p className="text-xs text-muted-foreground">{t('matches.homeHelp')}</p>
                        {preferredHint !== null && <p className="mt-1 text-xs font-medium text-primary">{preferredHint}</p>}
                    </div>
                    <FormField id="match-field" label={t('matches.field')} error={err('field_area_id')}>
                        <NativeSelect id="match-field" aria-invalid={err('field_area_id') !== undefined} {...register('field_area_id')}>
                            <option value="">{t('matches.noField')}</option>
                            {areasFor(format, reference.field_areas).map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    {occupationEnd !== null && <p className="text-sm text-muted-foreground">{t('matches.occupation', { time: occupationEnd })}</p>}
                    <label className="flex items-start gap-2 text-sm">
                        <input type="checkbox" className="mt-0.5 size-4 accent-primary" {...register('is_locked')} />
                        <span>
                            {t('matches.locked')}
                            <span className="block text-xs text-muted-foreground">{t('matches.lockedHelp')}</span>
                        </span>
                    </label>
                    <FormField id="match-override" label={t('matches.overrideReason')} error={err('rule_override_reason')} help={t('common.optional')}>
                        <Input id="match-override" {...register('rule_override_reason')} />
                    </FormField>
                </section>
            )}

            {isAway && (
                <section className="grid gap-4 rounded-lg border p-3" aria-labelledby="match-away-title">
                    <h3 id="match-away-title" className="flex items-center gap-2 text-sm font-semibold">
                        <Bus className="size-4" aria-hidden />
                        {t('matches.awaySection')}
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="match-venue" label={t('matches.venue')} error={err('venue_name')}>
                            <Input id="match-venue" {...register('venue_name')} />
                        </FormField>
                        <FormField id="match-municipality" label={t('matches.municipality')} error={err('municipality')}>
                            <Input id="match-municipality" {...register('municipality')} />
                        </FormField>
                    </div>
                    <FormField id="match-address" label={t('matches.address')} error={err('address')}>
                        <Input id="match-address" {...register('address')} />
                    </FormField>
                    <FormField id="match-maps" label={t('matches.maps')} error={err('maps_url')} help={t('common.optional')}>
                        <Input id="match-maps" type="url" inputMode="url" placeholder="https://maps.google.com/…" {...register('maps_url')} />
                    </FormField>
                </section>
            )}

            <section className="grid gap-3 rounded-lg border p-3" aria-labelledby="match-result-title">
                <div>
                    <h3 id="match-result-title" className="text-sm font-semibold">
                        {t('matches.result')}
                    </h3>
                    <p className="text-xs text-muted-foreground">{t('matches.resultHelp')}</p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="match-score-team" label={t('matches.scoreTeam', { team: team.name })} error={err('score_team')}>
                        <Input id="match-score-team" inputMode="numeric" maxLength={2} aria-invalid={err('score_team') !== undefined} {...register('score_team')} />
                    </FormField>
                    <FormField id="match-score-opponent" label={t('matches.scoreOpponent')} error={err('score_opponent')}>
                        <Input id="match-score-opponent" inputMode="numeric" maxLength={2} aria-invalid={err('score_opponent') !== undefined} {...register('score_opponent')} />
                    </FormField>
                </div>
            </section>

            <>
                <FormField id="match-notes" label={t('matches.notes')} error={err('notes')}>
                    <Textarea id="match-notes" rows={2} {...register('notes')} />
                </FormField>
                <FormField id="match-whatsapp" label={t('matches.whatsappExtra')} error={err('whatsapp_extra')}>
                    <Textarea id="match-whatsapp" rows={2} {...register('whatsapp_extra')} />
                </FormField>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" className="size-4 accent-primary" {...register('show_in_image')} />
                    {t('matches.showInImage')}
                </label>
            </>

            <SheetFooter className="flex-row flex-wrap justify-between gap-2 px-0">
                {match !== null ? (
                    <Button type="button" variant="ghost" className="text-destructive" onClick={() => setConfirmDelete(true)}>
                        <Trash2 aria-hidden />
                        {t('common.delete')}
                    </Button>
                ) : (
                    <span />
                )}
                <div className="flex gap-2">
                    <Button type="button" variant="outline" onClick={onDone}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" disabled={save.isPending}>
                        {save.isPending ? t('common.saving') : t('common.save')}
                    </Button>
                </div>
            </SheetFooter>

            <ConfirmDialog
                open={confirmDelete}
                title={t('matches.deleteConfirmTitle')}
                pending={remove.isPending}
                onCancel={() => setConfirmDelete(false)}
                onConfirm={() =>
                    match !== null &&
                    remove.mutate(match.id, {
                        onSuccess: () => {
                            toast.success(t('matches.deleted'));
                            setConfirmDelete(false);
                            onDone();
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </form>
    );
}
