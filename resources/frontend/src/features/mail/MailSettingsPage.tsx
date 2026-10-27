import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Send } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ApiError } from '@/api/client';
import { mailSettingsApi, type MailEncryption, type MailSettings } from '@/api/communications';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { PageHeader } from '@/components/PageHeader';
import { PasswordInput } from '@/components/PasswordInput';
import { ErrorState, LoadingState } from '@/components/QueryState';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';

const MAIL_KEY = ['mail-settings'] as const;

/**
 * Configuració del correu (servidor SMTP i remitent). Només l'administrador la veu.
 */
export function MailSettingsPage() {
    const settings = useQuery({ queryKey: MAIL_KEY, queryFn: mailSettingsApi.get });

    return (
        <>
            <PageHeader title={t('mail.title')} description={t('mail.subtitle')} />
            {settings.isPending ? <LoadingState /> : settings.isError ? <ErrorState /> : <MailForm key={settings.dataUpdatedAt} settings={settings.data} />}
        </>
    );
}

function MailForm({ settings }: { settings: MailSettings }) {
    const queryClient = useQueryClient();
    const [values, setValues] = useState({
        host: settings.host,
        port: String(settings.port),
        encryption: settings.encryption,
        username: settings.username,
        password: '',
        from_address: settings.from_address,
        from_name: settings.from_name,
    });
    const [testTo, setTestTo] = useState('');
    const set = (key: keyof typeof values) => (e: { target: { value: string } }) => setValues((v) => ({ ...v, [key]: e.target.value }));

    const save = useMutation({
        mutationFn: () => mailSettingsApi.update({ ...values, port: Number(values.port), encryption: values.encryption }),
        onSuccess: (data) => {
            queryClient.setQueryData(MAIL_KEY, data);
            toast.success(t('mail.saved'));
        },
    });
    const test = useMutation({
        mutationFn: () => mailSettingsApi.test(testTo.trim() === '' ? null : testTo.trim()),
        onSuccess: (data) => toast.success(t('mail.testSent', { email: data.sent_to })),
        onError: (e) => toast.error(errorMessage(e)),
    });
    const error = (field: string) => (save.error instanceof ApiError ? save.error.firstError(field) : undefined);

    return (
        <Card>
            <CardContent className="grid gap-6">
                <form
                    className="grid gap-4"
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        save.mutate();
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem_10rem]">
                        <FormField id="mail-host" label={t('mail.host')} error={error('host')}>
                            <Input id="mail-host" value={values.host} onChange={set('host')} autoComplete="off" />
                        </FormField>
                        <FormField id="mail-port" label={t('mail.port')} error={error('port')}>
                            <Input id="mail-port" inputMode="numeric" value={values.port} onChange={set('port')} />
                        </FormField>
                        <FormField id="mail-encryption" label={t('mail.encryption')} error={error('encryption')}>
                            <NativeSelect id="mail-encryption" value={values.encryption} onChange={(e) => setValues((v) => ({ ...v, encryption: e.target.value as MailEncryption }))}>
                                <option value="tls">{t('mail.encryptions.tls')}</option>
                                <option value="ssl">{t('mail.encryptions.ssl')}</option>
                                <option value="none">{t('mail.encryptions.none')}</option>
                            </NativeSelect>
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="mail-username" label={t('mail.username')} error={error('username')}>
                            <Input id="mail-username" value={values.username} onChange={set('username')} autoComplete="off" />
                        </FormField>
                        <FormField id="mail-password" label={t('mail.password')} help={settings.has_password ? t('mail.passwordKeep') : undefined} error={error('password')}>
                            <PasswordInput id="mail-password" value={values.password} onChange={set('password')} autoComplete="new-password" />
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="mail-from-address" label={t('mail.fromAddress')} error={error('from_address')}>
                            <Input id="mail-from-address" type="email" value={values.from_address} onChange={set('from_address')} />
                        </FormField>
                        <FormField id="mail-from-name" label={t('mail.fromName')} error={error('from_name')}>
                            <Input id="mail-from-name" value={values.from_name} onChange={set('from_name')} />
                        </FormField>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={save.isPending}>
                            {t('common.save')}
                        </Button>
                    </div>
                </form>

                <div className="grid gap-3 border-t pt-4">
                    <p className="text-sm font-medium">{t('mail.testTitle')}</p>
                    <p className="text-sm text-muted-foreground">{t('mail.testHelp')}</p>
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid min-w-64 flex-1 gap-1.5">
                            <label htmlFor="mail-test-to" className="text-sm font-medium">
                                {t('mail.testTo')}
                            </label>
                            <Input id="mail-test-to" type="email" value={testTo} onChange={(e) => setTestTo(e.target.value)} />
                        </div>
                        <Button type="button" variant="outline" onClick={() => test.mutate()} disabled={test.isPending}>
                            <Send aria-hidden />
                            {t('mail.testSend')}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
