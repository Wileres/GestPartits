import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ImageUp, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ApiError } from '@/api/client';
import { settingsApi, type BrandingSettings } from '@/api/communications';
import { ClubLogo } from '@/components/ClubLogo';
import { FormField } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/QueryState';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';

export function AppearancePage() {
    const settings = useQuery({ queryKey: ['settings'], queryFn: settingsApi.get });

    return (
        <>
            <PageHeader title={t('appearance.title')} description={t('appearance.subtitle')} />
            {settings.isPending ? <LoadingState /> : settings.isError ? <ErrorState /> : <AppearanceForm key={settings.dataUpdatedAt} settings={settings.data} />}
        </>
    );
}

function AppearanceForm({ settings }: { settings: BrandingSettings }) {
    const queryClient = useQueryClient();
    const input = useRef<HTMLInputElement>(null);
    const [values, setValues] = useState({ club_name: settings.club_name, primary_color: settings.primary_color, primary_dark_color: settings.primary_dark_color });
    const onSaved = (data: BrandingSettings) => {
        queryClient.setQueryData(['settings'], data);
        toast.success(t('appearance.saved'));
    };
    const save = useMutation({ mutationFn: () => settingsApi.update(values), onSuccess: onSaved });
    const upload = useMutation({ mutationFn: (file: File) => settingsApi.uploadLogo(file), onSuccess: onSaved, onError: (e) => toast.error(errorMessage(e)) });
    const remove = useMutation({ mutationFn: settingsApi.deleteLogo, onSuccess: onSaved, onError: (e) => toast.error(errorMessage(e)) });
    const error = (field: string) => (save.error instanceof ApiError ? save.error.firstError(field) : undefined);

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <Card>
                <CardContent>
                    <form
                        className="grid gap-4"
                        noValidate
                        onSubmit={(e) => {
                            e.preventDefault();
                            save.mutate();
                        }}
                    >
                        <FormField id="club-name" label={t('appearance.clubName')} error={error('club_name')}>
                            <Input id="club-name" value={values.club_name} onChange={(e) => setValues((v) => ({ ...v, club_name: e.target.value }))} />
                        </FormField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {(['primary_color', 'primary_dark_color'] as const).map((key) => (
                                <FormField key={key} id={`club-${key}`} label={key === 'primary_color' ? t('appearance.primary') : t('appearance.primaryDark')} error={error(key)}>
                                    <div className="flex gap-2">
                                        <input type="color" aria-label={key === 'primary_color' ? t('appearance.primary') : t('appearance.primaryDark')} className="h-9 w-14 cursor-pointer rounded border" value={values[key]} onChange={(e) => setValues((v) => ({ ...v, [key]: e.target.value.toUpperCase() }))} />
                                        <Input id={`club-${key}`} value={values[key]} onChange={(e) => setValues((v) => ({ ...v, [key]: e.target.value }))} />
                                    </div>
                                </FormField>
                            ))}
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={save.isPending}>
                                {t('common.save')}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="grid gap-3">
                    <p className="text-sm font-medium">{t('appearance.logo')}</p>
                    <div className="flex aspect-[9/16] max-h-80 flex-col items-center justify-start gap-3 rounded-lg p-6 text-white" style={{ background: `linear-gradient(170deg, ${values.primary_color}, ${values.primary_dark_color})` }}>
                        {settings.logo_url !== null ? <img src={settings.logo_url} alt="" className="h-24 object-contain" /> : <ClubLogo className="size-24" />}
                        <p className="text-center text-sm font-semibold uppercase">{values.club_name}</p>
                    </div>
                    <input ref={input} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => e.target.files?.[0] !== undefined && upload.mutate(e.target.files[0])} />
                    <Button type="button" variant="outline" onClick={() => input.current?.click()} disabled={upload.isPending}>
                        <ImageUp aria-hidden />
                        {t('appearance.upload')}
                    </Button>
                    {settings.has_logo && (
                        <Button type="button" variant="ghost" onClick={() => remove.mutate()} disabled={remove.isPending}>
                            <Trash2 aria-hidden />
                            {t('appearance.remove')}
                        </Button>
                    )}
                    <p className="text-xs text-muted-foreground">{t('appearance.logoHelp')}</p>
                </CardContent>
            </Card>
        </div>
    );
}
