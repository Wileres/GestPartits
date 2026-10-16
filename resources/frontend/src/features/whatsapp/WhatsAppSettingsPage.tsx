import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    destinationsApi,
    templatesApi,
    type DestinationInput,
    type DestinationType,
    type ProviderKey,
    type TemplateAppliesTo,
    type TemplateInput,
    type TemplateKind,
    type WhatsAppDestination,
    type WhatsAppTemplate,
} from '@/api/communications';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormField } from '@/components/FormField';
import { NativeSelect } from '@/components/NativeSelect';
import { PageHeader } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/QueryState';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeams } from '@/features/teams/useTeams';
import { t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';
import { ApiError } from '@/api/client';

export function WhatsAppSettingsPage() {
    return (
        <>
            <PageHeader title={t('whatsapp.title')} description={t('whatsapp.subtitle')} />
            <div className="grid gap-6 xl:grid-cols-2">
                <Templates />
                <Destinations />
            </div>
        </>
    );
}

function fieldError(error: unknown, field: string): string | undefined {
    return error instanceof ApiError ? error.firstError(field) : undefined;
}

/* Plantilles ------------------------------------------------------------------------------------- */

function Templates() {
    const queryClient = useQueryClient();
    const templates = useQuery({ queryKey: ['whatsapp-templates'], queryFn: templatesApi.list });
    const variables = useQuery({ queryKey: ['whatsapp-templates', 'variables'], queryFn: templatesApi.variables });
    const [editing, setEditing] = useState<WhatsAppTemplate | null | undefined>(undefined);
    const [deleting, setDeleting] = useState<WhatsAppTemplate | null>(null);
    const remove = useMutation({
        mutationFn: (id: number) => templatesApi.remove(id),
        onSuccess: () => {
            toast.success(t('whatsapp.deleted'));
            setDeleting(null);
            void queryClient.invalidateQueries({ queryKey: ['whatsapp-templates'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                <CardTitle>
                    <h2>{t('whatsapp.templates')}</h2>
                </CardTitle>
                <Button size="sm" onClick={() => setEditing(null)}>
                    <Plus aria-hidden />
                    {t('whatsapp.createTemplate')}
                </Button>
            </CardHeader>
            <CardContent>
                {templates.isPending ? (
                    <LoadingState />
                ) : templates.isError ? (
                    <ErrorState />
                ) : (
                    <ul className="grid gap-3">
                        {templates.data.map((template) => (
                            <li key={template.id} className="rounded-lg border p-3">
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <p className="font-medium">{template.name}</p>
                                    <Badge variant="outline">{t(`whatsapp.kinds.${template.kind}`)}</Badge>
                                    {template.kind === 'match' && <Badge variant="outline">{t(`whatsapp.appliesToLabels.${template.applies_to}`)}</Badge>}
                                    {!template.is_active && <Badge variant="outline">{t('common.inactive')}</Badge>}
                                    <span className="ml-auto flex gap-1">
                                        <Button variant="ghost" size="icon" onClick={() => setEditing(template)} aria-label={`${t('common.edit')}: ${template.name}`}>
                                            <Pencil aria-hidden />
                                        </Button>
                                        <Button variant="ghost" size="icon" onClick={() => setDeleting(template)} aria-label={`${t('common.delete')}: ${template.name}`}>
                                            <Trash2 className="text-destructive" aria-hidden />
                                        </Button>
                                    </span>
                                </div>
                                <pre className="overflow-x-auto rounded bg-muted p-2 text-xs whitespace-pre-wrap">{template.body}</pre>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            <Dialog open={editing !== undefined} onOpenChange={(open) => !open && setEditing(undefined)}>
                <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editing === null ? t('whatsapp.createTemplate') : t('whatsapp.editTemplate')}</DialogTitle>
                    </DialogHeader>
                    {editing !== undefined && <TemplateForm template={editing} variables={variables.data} onDone={() => setEditing(undefined)} />}
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                open={deleting !== null}
                title={t('whatsapp.deleteConfirmTitle', { name: deleting?.name ?? '' })}
                pending={remove.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() => deleting !== null && remove.mutate(deleting.id)}
            />
        </Card>
    );
}

function TemplateForm({ template, variables, onDone }: { template: WhatsAppTemplate | null; variables: { match: string[]; weekend_summary: string[]; line: string[] } | undefined; onDone: () => void }) {
    const queryClient = useQueryClient();
    const [values, setValues] = useState<TemplateInput>({
        name: template?.name ?? '',
        kind: template?.kind ?? 'match',
        applies_to: template?.applies_to ?? 'any',
        body: template?.body ?? '',
        line_body: template?.line_body ?? null,
        provider_template_name: template?.provider_template_name ?? null,
        is_active: template?.is_active ?? true,
    });
    const save = useMutation({
        mutationFn: () => (template === null ? templatesApi.create(values) : templatesApi.update(template.id, values)),
        onSuccess: () => {
            toast.success(t('whatsapp.saved'));
            void queryClient.invalidateQueries({ queryKey: ['whatsapp-templates'] });
            onDone();
        },
    });
    const set = <K extends keyof TemplateInput>(key: K, value: TemplateInput[K]) => setValues((v) => ({ ...v, [key]: value }));
    const available = values.kind === 'match' ? variables?.match : variables?.weekend_summary;

    return (
        <form
            className="grid gap-4"
            noValidate
            onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
            }}
        >
            {save.isError && !(save.error instanceof ApiError && Object.keys(save.error.errors).length > 0) && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{errorMessage(save.error)}</AlertDescription>
                </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-3">
                <FormField id="tpl-name" label={t('whatsapp.name')} error={fieldError(save.error, 'name')}>
                    <Input id="tpl-name" value={values.name} onChange={(e) => set('name', e.target.value)} />
                </FormField>
                <FormField id="tpl-kind" label={t('whatsapp.kind')}>
                    <NativeSelect id="tpl-kind" value={values.kind} onChange={(e) => set('kind', e.target.value as TemplateKind)}>
                        <option value="match">{t('whatsapp.kinds.match')}</option>
                        <option value="weekend_summary">{t('whatsapp.kinds.weekend_summary')}</option>
                    </NativeSelect>
                </FormField>
                {values.kind === 'match' && (
                    <FormField id="tpl-applies" label={t('whatsapp.appliesTo')}>
                        <NativeSelect id="tpl-applies" value={values.applies_to} onChange={(e) => set('applies_to', e.target.value as TemplateAppliesTo)}>
                            {(['home', 'away', 'any'] as const).map((v) => (
                                <option key={v} value={v}>
                                    {t(`whatsapp.appliesToLabels.${v}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                )}
            </div>
            <FormField id="tpl-body" label={t('whatsapp.body')} error={fieldError(save.error, 'body')} help={t('whatsapp.variablesHelp')}>
                <Textarea id="tpl-body" rows={10} className="font-mono text-sm" value={values.body} onChange={(e) => set('body', e.target.value)} />
            </FormField>
            {available !== undefined && (
                <div>
                    <p className="mb-1 text-sm font-medium">{t('whatsapp.variables')}</p>
                    <div className="flex flex-wrap gap-1">
                        {available.map((name) => (
                            <button key={name} type="button" className="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs hover:bg-secondary" onClick={() => set('body', `${values.body}{{${name}}}`)}>
                                {`{{${name}}}`}
                            </button>
                        ))}
                    </div>
                </div>
            )}
            {values.kind === 'weekend_summary' && (
                <FormField id="tpl-line" label={t('whatsapp.lineBody')} error={fieldError(save.error, 'line_body')} help={variables?.line.map((v) => `{{${v}}}`).join(' ')}>
                    <Input id="tpl-line" className="font-mono text-sm" value={values.line_body ?? ''} onChange={(e) => set('line_body', e.target.value === '' ? null : e.target.value)} />
                </FormField>
            )}
            <FormField id="tpl-meta" label={t('whatsapp.metaTemplate')} error={fieldError(save.error, 'provider_template_name')} help={t('whatsapp.metaTemplateHelp')}>
                <Input id="tpl-meta" value={values.provider_template_name ?? ''} onChange={(e) => set('provider_template_name', e.target.value === '' ? null : e.target.value)} />
            </FormField>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" checked={values.is_active} onChange={(e) => set('is_active', e.target.checked)} />
                {t('whatsapp.active')}
            </label>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={save.isPending}>
                    {t('common.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}

/* Destins ---------------------------------------------------------------------------------------- */

function Destinations() {
    const queryClient = useQueryClient();
    const destinations = useQuery({ queryKey: ['whatsapp-destinations'], queryFn: destinationsApi.list });
    const [editing, setEditing] = useState<WhatsAppDestination | null | undefined>(undefined);
    const [deleting, setDeleting] = useState<WhatsAppDestination | null>(null);
    const remove = useMutation({
        mutationFn: (id: number) => destinationsApi.remove(id),
        onSuccess: () => {
            toast.success(t('whatsapp.deleted'));
            setDeleting(null);
            void queryClient.invalidateQueries({ queryKey: ['whatsapp-destinations'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                <CardTitle>
                    <h2>{t('whatsapp.destinations')}</h2>
                </CardTitle>
                <Button size="sm" onClick={() => setEditing(null)}>
                    <Plus aria-hidden />
                    {t('whatsapp.createDestination')}
                </Button>
            </CardHeader>
            <CardContent>
                {destinations.isPending ? (
                    <LoadingState />
                ) : destinations.isError ? (
                    <ErrorState />
                ) : destinations.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('whatsapp.noDestinations')}</p>
                ) : (
                    <ul className="divide-y">
                        {destinations.data.map((d) => (
                            <li key={d.id} className="flex flex-wrap items-center gap-2 py-2">
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {d.scope === 'club' ? t('whatsapp.scopeClub') : d.team} · {d.label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {d.destination_type_label} · {d.provider_label}
                                        {d.external_id_hint !== null && ` · ${d.external_id_hint}`}
                                        {!d.is_active && ` · ${t('common.inactive')}`}
                                    </p>
                                </div>
                                <Button variant="ghost" size="icon" onClick={() => setEditing(d)} aria-label={`${t('common.edit')}: ${d.label}`}>
                                    <Pencil aria-hidden />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => setDeleting(d)} aria-label={`${t('common.delete')}: ${d.label}`}>
                                    <Trash2 className="text-destructive" aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            <Dialog open={editing !== undefined} onOpenChange={(open) => !open && setEditing(undefined)}>
                <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editing === null ? t('whatsapp.createDestination') : t('whatsapp.editDestination')}</DialogTitle>
                    </DialogHeader>
                    {editing !== undefined && <DestinationForm destination={editing} onDone={() => setEditing(undefined)} />}
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                open={deleting !== null}
                title={t('whatsapp.deleteConfirmTitle', { name: deleting?.label ?? '' })}
                pending={remove.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() => deleting !== null && remove.mutate(deleting.id)}
            />
        </Card>
    );
}

function DestinationForm({ destination, onDone }: { destination: WhatsAppDestination | null; onDone: () => void }) {
    const queryClient = useQueryClient();
    const { data: teams = [] } = useTeams();
    const providers = useQuery({ queryKey: ['whatsapp-destinations', 'providers'], queryFn: destinationsApi.providers });
    const [values, setValues] = useState<DestinationInput>({
        scope: destination?.scope ?? 'team',
        team_id: destination?.team_id ?? teams[0]?.id ?? null,
        label: destination?.label ?? '',
        provider: destination?.provider ?? 'manual',
        destination_type: destination?.destination_type ?? 'group',
        external_id: null,
        invite_url: destination?.invite_url ?? null,
        include_images: destination?.include_images ?? false,
        is_active: destination?.is_active ?? true,
    });
    const save = useMutation({
        mutationFn: () => (destination === null ? destinationsApi.create(values) : destinationsApi.update(destination.id, values)),
        onSuccess: () => {
            toast.success(t('whatsapp.saved'));
            void queryClient.invalidateQueries({ queryKey: ['whatsapp-destinations'] });
            onDone();
        },
    });
    const set = <K extends keyof DestinationInput>(key: K, value: DestinationInput[K]) => setValues((v) => ({ ...v, [key]: value }));
    const provider = providers.data?.find((p) => p.key === values.provider);
    const types: DestinationType[] = (provider?.destination_types ?? ['group', 'channel', 'phone', 'list']).filter((type) => values.scope === 'club' || type !== 'channel');
    const keepHint = destination?.external_id_hint ?? null;

    return (
        <form
            className="grid gap-4"
            noValidate
            onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="dst-scope" label={t('whatsapp.scope')}>
                    <NativeSelect id="dst-scope" value={values.scope} onChange={(e) => set('scope', e.target.value as 'team' | 'club')}>
                        <option value="team">{t('whatsapp.scopeTeam')}</option>
                        <option value="club">{t('whatsapp.scopeClub')}</option>
                    </NativeSelect>
                </FormField>
                {values.scope === 'team' && (
                    <FormField id="dst-team" label={t('whatsapp.team')} error={fieldError(save.error, 'team_id')}>
                        <NativeSelect id="dst-team" value={values.team_id ?? ''} onChange={(e) => set('team_id', Number(e.target.value))}>
                            {teams.map((team) => (
                                <option key={team.id} value={team.id}>
                                    {team.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                )}
            </div>
            <FormField id="dst-label" label={t('whatsapp.label')} error={fieldError(save.error, 'label')}>
                <Input id="dst-label" value={values.label} onChange={(e) => set('label', e.target.value)} />
            </FormField>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="dst-provider" label={t('whatsapp.provider')} error={fieldError(save.error, 'provider')}>
                    <NativeSelect id="dst-provider" value={values.provider} onChange={(e) => set('provider', e.target.value as ProviderKey)}>
                        {(providers.data ?? [{ key: 'manual' as ProviderKey }]).map((p) => (
                            <option key={p.key} value={p.key}>
                                {t(`whatsapp.providers.${p.key}`)}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField id="dst-type" label={t('whatsapp.type')} error={fieldError(save.error, 'destination_type')}>
                    <NativeSelect id="dst-type" value={values.destination_type} onChange={(e) => set('destination_type', e.target.value as DestinationType)}>
                        {types.map((type) => (
                            <option key={type} value={type}>
                                {t(`whatsapp.types.${type}`)}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            </div>
            {provider?.manual === true && <p className="text-xs text-muted-foreground">{t('whatsapp.manualProvider')}</p>}
            <FormField
                id="dst-external"
                label={t('whatsapp.externalId')}
                error={fieldError(save.error, 'external_id')}
                help={keepHint === null ? t('whatsapp.externalIdHelp') : t('whatsapp.externalIdKeep', { hint: keepHint })}
            >
                <Input id="dst-external" autoComplete="off" value={values.external_id ?? ''} onChange={(e) => set('external_id', e.target.value === '' ? null : e.target.value)} />
            </FormField>
            <FormField id="dst-invite" label={t('whatsapp.inviteUrl')} error={fieldError(save.error, 'invite_url')} help={t('whatsapp.inviteUrlHelp')}>
                <Input id="dst-invite" type="url" value={values.invite_url ?? ''} onChange={(e) => set('invite_url', e.target.value === '' ? null : e.target.value)} />
            </FormField>
            {values.scope === 'club' && (
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" className="size-4 accent-primary" checked={values.include_images} onChange={(e) => set('include_images', e.target.checked)} />
                    {t('whatsapp.includeImages')}
                </label>
            )}
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-primary" checked={values.is_active} onChange={(e) => set('is_active', e.target.checked)} />
                {t('common.active')}
            </label>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={save.isPending}>
                    {t('common.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
