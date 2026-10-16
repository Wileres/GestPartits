import { zodResolver } from '@hookform/resolvers/zod';
import { ImageUp, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { Category, Team } from '@/api/types';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { errorMessage, numberOrNull } from '@/lib/errors';
import { applyServerErrors } from '@/lib/forms';
import { useSaveTeam, useTeamLogo } from './useTeams';

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
});

type FormValues = z.infer<typeof schema>;

const fields = ['name', 'category_id', 'game_format', 'color', 'match_duration_min', 'post_min', 'is_active'] as const;

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
        },
    });

    const categoryId = useWatch({ control, name: 'category_id' });
    const category = categories.find((c) => String(c.id) === categoryId);
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
                },
            },
            {
                onSuccess: () => {
                    toast.success(team === null ? t('teams.created') : t('teams.updated'));
                    onDone();
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, fields)),
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
            {team !== null && <LogoField team={team} />}
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

function LogoField({ team }: { team: Team }) {
    const { upload, remove } = useTeamLogo();
    const input = useRef<HTMLInputElement>(null);

    const onFile = (file: File | undefined) => {
        if (file === undefined) return;
        upload.mutate(
            { id: team.id, file },
            {
                onSuccess: () => toast.success(t('teams.logoUploaded')),
                onError: (error) => toast.error(errorMessage(error)),
            },
        );
    };

    return (
        <div className="grid gap-2 rounded-lg border p-3">
            <p className="text-sm font-medium">{t('teams.logo')}</p>
            <div className="flex flex-wrap items-center gap-3">
                {team.logo_url !== null && <img src={team.logo_url} alt="" className="size-12 rounded border bg-white object-contain" />}
                <input ref={input} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => onFile(e.target.files?.[0])} />
                <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()} disabled={upload.isPending}>
                    <ImageUp aria-hidden />
                    {t('teams.uploadLogo')}
                </Button>
                {team.has_logo && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => remove.mutate(team.id)} disabled={remove.isPending}>
                        <Trash2 aria-hidden />
                        {t('teams.deleteLogo')}
                    </Button>
                )}
            </div>
            <p className="text-xs text-muted-foreground">{t('teams.logoHelp')}</p>
        </div>
    );
}
