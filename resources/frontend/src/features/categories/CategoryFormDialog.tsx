import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import type { Category } from '@/api/types';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { describedBy } from '@/lib/a11y';
import { applyServerErrors } from '@/lib/forms';
import { useSaveCategory } from './useCategories';

const integer = (min: number, max: number) =>
    z.string().regex(/^\d+$/, t('common.invalidNumber')).refine((v) => Number(v) >= min && Number(v) <= max, t('common.invalidNumber'));

const schema = z.object({
    name: z.string().trim().min(1, t('common.required')),
    game_format: z.enum(['f7', 'f11']),
    match_duration_min: integer(1, 300),
    post_min: integer(0, 120),
    min_gap_min: integer(0, 120),
    sort_order: integer(0, 65535),
    is_active: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

const fields = ['name', 'game_format', 'match_duration_min', 'post_min', 'min_gap_min', 'sort_order', 'is_active'] as const;

interface Props {
    open: boolean;
    category: Category | null;
    onOpenChange: (open: boolean) => void;
}

export function CategoryFormDialog({ open, category, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{category === null ? t('categories.create') : t('categories.edit')}</DialogTitle>
                </DialogHeader>
                {open && <CategoryForm category={category} onDone={() => onOpenChange(false)} />}
            </DialogContent>
        </Dialog>
    );
}

function CategoryForm({ category, onDone }: { category: Category | null; onDone: () => void }) {
    const save = useSaveCategory();
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
            name: category?.name ?? '',
            game_format: category?.game_format ?? 'f7',
            match_duration_min: String(category?.match_duration_min ?? 60),
            post_min: String(category?.post_min ?? 10),
            min_gap_min: String(category?.min_gap_min ?? 0),
            sort_order: String(category?.sort_order ?? 100),
            is_active: category?.is_active ?? true,
        },
    });

    const total = Number(useWatch({ control, name: 'match_duration_min' })) + Number(useWatch({ control, name: 'post_min' }));

    const onSubmit = handleSubmit((values) => {
        setFormError(null);
        save.mutate(
            {
                id: category?.id ?? null,
                input: {
                    ...values,
                    match_duration_min: Number(values.match_duration_min),
                    post_min: Number(values.post_min),
                    min_gap_min: Number(values.min_gap_min),
                    sort_order: Number(values.sort_order),
                },
            },
            {
                onSuccess: () => {
                    toast.success(category === null ? t('categories.created') : t('categories.updated'));
                    onDone();
                },
                onError: (error) => setFormError(applyServerErrors(error, setError, fields)),
            },
        );
    });

    const numberField = (name: 'match_duration_min' | 'post_min' | 'min_gap_min' | 'sort_order', label: string, help?: string) => (
        <FormField id={`category-${name}`} label={label} error={errors[name]?.message} help={help}>
            <Input
                id={`category-${name}`}
                inputMode="numeric"
                aria-invalid={errors[name] !== undefined}
                aria-describedby={describedBy(`category-${name}`, errors[name]?.message, help)}
                {...register(name)}
            />
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
                <FormField id="category-name" label={t('categories.name')} error={errors.name?.message}>
                    <Input id="category-name" aria-invalid={errors.name !== undefined} {...register('name')} />
                </FormField>
                <FormField id="category-format" label={t('categories.format')} error={errors.game_format?.message}>
                    <NativeSelect id="category-format" {...register('game_format')}>
                        <option value="f7">{t('labels.format.f7')}</option>
                        <option value="f11">{t('labels.format.f11')}</option>
                    </NativeSelect>
                </FormField>
                {numberField('match_duration_min', t('categories.duration'), t('categories.durationHelp'))}
                {numberField('post_min', t('categories.post'), t('categories.postHelp'))}
                {numberField('min_gap_min', t('categories.minGap'))}
                {numberField('sort_order', t('categories.order'))}
            </div>
            {Number.isFinite(total) && <p className="text-sm text-muted-foreground">{t('categories.occupation', { total })}</p>}
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" {...register('is_active')} />
                {t('common.active')}
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
