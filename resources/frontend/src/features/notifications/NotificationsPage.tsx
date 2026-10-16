import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCheck, Copy, ExternalLink, MessageCircle, RefreshCw, RotateCcw, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { toast } from 'sonner';
import { messagesApi, type MessageStatus, type WhatsAppMessage } from '@/api/communications';
import { MatchdaySelector } from '@/components/MatchdaySelector';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useSchedule } from '@/features/schedule/useSchedule';
import { useMatchdaySelection } from '@/hooks/useMatchdaySelection';
import { formatDateTime, t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';
import { cn } from '@/lib/utils';

const statusStyle: Record<MessageStatus, string> = {
    draft: 'border-muted-foreground/30 bg-muted text-muted-foreground',
    queued: 'border-primary/30 bg-secondary text-primary',
    sending: 'border-primary/30 bg-secondary text-primary',
    sent: 'border-status-valid/40 bg-status-valid-soft text-status-valid',
    manual_sent: 'border-status-valid/40 bg-status-valid-soft text-status-valid',
    failed: 'border-status-critical/40 bg-status-critical-soft text-status-critical',
    unknown: 'border-status-warning/40 bg-status-warning-soft text-status-warning',
    outdated: 'border-muted-foreground/30 bg-muted text-muted-foreground line-through',
    discarded: 'border-muted-foreground/30 bg-muted text-muted-foreground line-through',
};

const HISTORY: MessageStatus[] = ['outdated', 'discarded'];

export function NotificationsPage() {
    const selection = useMatchdaySelection();

    return (
        <>
            <PageHeader title={t('notifications.title')} description={t('notifications.subtitle')} />
            <div className="mb-4">
                <MatchdaySelector selection={selection} />
            </div>
            {selection.isPending ? (
                <LoadingState />
            ) : selection.matchday === null ? (
                <EmptyState title={t('schedule.emptyTitle')} />
            ) : (
                <MatchdayNotifications key={selection.matchday.id} matchdayId={selection.matchday.id} />
            )}
        </>
    );
}

function MatchdayNotifications({ matchdayId }: { matchdayId: number }) {
    const schedule = useSchedule(matchdayId);

    if (schedule.isPending) return <LoadingState />;
    if (schedule.isError) return <ErrorState />;

    if (schedule.data.status !== 'confirmed') {
        return (
            <Alert className="border-status-warning/40 bg-status-warning-soft text-status-warning">
                <AlertDescription className="flex flex-wrap items-center justify-between gap-2 text-status-warning">
                    {t('images.needsConfirmation')}
                    <Button asChild size="sm" variant="outline">
                        <Link to={`/?jornada=${matchdayId}`}>{t('schedule.title')}</Link>
                    </Button>
                </AlertDescription>
            </Alert>
        );
    }

    return <Messages scheduleId={schedule.data.id} />;
}

function Messages({ scheduleId }: { scheduleId: number }) {
    const queryClient = useQueryClient();
    const key = ['schedules', scheduleId, 'whatsapp'] as const;
    const [selected, setSelected] = useState<number[]>([]);
    const [confirming, setConfirming] = useState(false);

    const messages = useQuery({
        queryKey: key,
        queryFn: () => messagesApi.list(scheduleId),
        // Mentre hi ha missatges a la cua o enviant-se, es consulta l'estat.
        refetchInterval: (query) => (query.state.data?.some((m) => m.status === 'queued' || m.status === 'sending') === true ? 2000 : false),
    });
    const setMessages = (list: WhatsAppMessage[]) => queryClient.setQueryData(key, list);
    const replace = (message: WhatsAppMessage) => queryClient.setQueryData<WhatsAppMessage[]>(key, (list) => list?.map((m) => (m.id === message.id ? message : m)));

    const preview = useMutation({ mutationFn: () => messagesApi.preview(scheduleId), onSuccess: setMessages, onError: (e) => toast.error(errorMessage(e)) });
    const send = useMutation({
        mutationFn: (ids: number[]) => messagesApi.send(scheduleId, ids),
        onSuccess: (data) => {
            setMessages(data.messages);
            setSelected([]);
            setConfirming(false);
            toast.success(t('notifications.queued', { count: data.queued.length }));
        },
        onError: (e) => toast.error(errorMessage(e)),
    });
    const retry = useMutation({
        mutationFn: () => messagesApi.retryFailed(scheduleId),
        onSuccess: (data) => {
            setMessages(data.messages);
            toast.success(t('notifications.queued', { count: data.queued.length }));
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    if (messages.isPending) return <LoadingState />;
    if (messages.isError) return <ErrorState />;

    const current = messages.data.filter((m) => !HISTORY.includes(m.status));
    const history = messages.data.filter((m) => HISTORY.includes(m.status));
    const summaries = current.filter((m) => m.is_summary);
    const teamMessages = current.filter((m) => !m.is_summary);
    const failed = current.filter((m) => m.status === 'failed').length;
    const toSend = current.filter((m) => selected.includes(m.id) && m.can_send);

    const toggle = (id: number) => setSelected((list) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]));

    return (
        <>
            <div className="mb-4 flex flex-wrap gap-2">
                <Button onClick={() => preview.mutate()} disabled={preview.isPending}>
                    <RefreshCw aria-hidden />
                    {messages.data.length === 0 ? t('notifications.prepare') : t('notifications.refresh')}
                </Button>
                <Button variant="outline" onClick={() => setConfirming(true)} disabled={toSend.length === 0 || send.isPending}>
                    <Send aria-hidden />
                    {t('notifications.sendSelected', { count: toSend.length })}
                </Button>
                {failed > 0 && (
                    <Button variant="outline" onClick={() => retry.mutate()} disabled={retry.isPending}>
                        <RotateCcw aria-hidden />
                        {t('notifications.retryFailed', { count: failed })}
                    </Button>
                )}
            </div>

            {messages.data.length === 0 && (
                <EmptyState icon={<MessageCircle className="size-8 text-muted-foreground" aria-hidden />} title={t('notifications.empty')}>
                    <p className="max-w-md text-sm text-muted-foreground">{t('notifications.noDestinations')}</p>
                </EmptyState>
            )}

            {summaries.length > 0 && (
                <section className="mb-6" aria-labelledby="avisos-canal">
                    <h2 id="avisos-canal" className="mb-2 text-lg font-semibold">
                        {t('notifications.clubChannel')}
                    </h2>
                    <div className="grid gap-3 lg:grid-cols-2">
                        {summaries.map((m) => (
                            <MessageCard key={m.id} message={m} selected={selected.includes(m.id)} onToggle={() => toggle(m.id)} onChange={replace} />
                        ))}
                    </div>
                </section>
            )}

            {teamMessages.length > 0 && (
                <section aria-labelledby="avisos-equips">
                    <h2 id="avisos-equips" className="mb-2 text-lg font-semibold">
                        {t('notifications.teams')}
                    </h2>
                    <div className="grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
                        {teamMessages.map((m) => (
                            <MessageCard key={m.id} message={m} selected={selected.includes(m.id)} onToggle={() => toggle(m.id)} onChange={replace} />
                        ))}
                    </div>
                </section>
            )}

            {history.length > 0 && (
                <section className="mt-8" aria-labelledby="avisos-historial">
                    <h2 id="avisos-historial" className="mb-2 text-lg font-semibold">
                        {t('notifications.history')}
                    </h2>
                    <ul className="grid gap-1 text-sm">
                        {history.map((m) => (
                            <li key={m.id} className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2">
                                <Badge variant="outline" className={statusStyle[m.status]}>
                                    {t(`notifications.status.${m.status}`)}
                                </Badge>
                                <span className="font-medium">{m.team ?? t('notifications.clubChannel')}</span>
                                <span className="text-muted-foreground">→ {m.destination.label}</span>
                                {m.sent_at !== null && <span className="text-xs text-muted-foreground">{formatDateTime(m.sent_at)}</span>}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <Dialog open={confirming} onOpenChange={(open) => !open && setConfirming(false)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('notifications.confirmTitle', { count: toSend.length })}</DialogTitle>
                        <DialogDescription>{t('notifications.confirmHelp')}</DialogDescription>
                    </DialogHeader>
                    <ul className="grid max-h-60 gap-1 overflow-y-auto text-sm">
                        {toSend.map((m) => (
                            <li key={m.id} className="rounded-md bg-muted px-2 py-1">
                                {m.team ?? t('notifications.clubChannel')} → {m.destination.label} ({m.provider_label})
                            </li>
                        ))}
                    </ul>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirming(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={() => send.mutate(toSend.map((m) => m.id))} disabled={send.isPending}>
                            <Send aria-hidden />
                            {t('notifications.confirmSend')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

interface MessageCardProps {
    message: WhatsAppMessage;
    selected: boolean;
    onToggle: () => void;
    onChange: (message: WhatsAppMessage) => void;
}

function MessageCard({ message, selected, onToggle, onChange }: MessageCardProps) {
    const [body, setBody] = useState(message.body);
    const dirty = body !== message.body;
    const save = useMutation({ mutationFn: () => messagesApi.update(message.id, body), onSuccess: onChange, onError: (e) => toast.error(errorMessage(e)) });
    const markSent = useMutation({ mutationFn: () => messagesApi.markSent(message.id), onSuccess: onChange, onError: (e) => toast.error(errorMessage(e)) });
    const discard = useMutation({ mutationFn: () => messagesApi.discard(message.id), onSuccess: onChange, onError: (e) => toast.error(errorMessage(e)) });
    const textId = `missatge-${message.id}`;
    const isChannel = message.destination.type === 'channel';

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(message.body);
            toast.success(t('notifications.copied'));
        } catch {
            toast.error(t('common.error'));
        }
    };

    return (
        <Card className={cn('gap-3', selected && 'ring-2 ring-primary')}>
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <CardTitle className="text-base">
                        <h3>
                            {message.team ?? t('notifications.clubChannel')}
                            {message.opponent !== null && <span className="font-normal text-muted-foreground"> – {message.opponent}</span>}
                        </h3>
                    </CardTitle>
                    <Badge variant="outline" className={statusStyle[message.status]}>
                        {t(`notifications.status.${message.status}`)}
                    </Badge>
                </div>
                <p className="text-xs text-muted-foreground">
                    {message.destination.type_label} · {message.destination.label} · {message.provider_label}
                </p>
            </CardHeader>
            <CardContent className="grid gap-3">
                <label htmlFor={textId} className="sr-only">
                    {t('notifications.edit')}
                </label>
                <Textarea id={textId} value={body} onChange={(e) => setBody(e.target.value)} readOnly={!message.can_edit} rows={Math.min(14, body.split('\n').length + 1)} className="font-mono text-sm" />
                {message.attachments.length > 0 && (
                    <div>
                        <p className="mb-1 text-xs font-medium">{t('notifications.attachments')}</p>
                        <div className="flex gap-2">
                            {message.attachments.map((a) => (
                                <img key={a.id} src={a.url} alt="" className="h-24 rounded border" loading="lazy" />
                            ))}
                        </div>
                    </div>
                )}
                {message.status === 'failed' && message.error_message !== null && <p className="text-sm text-status-critical">{t('notifications.error', { message: message.error_message })}</p>}
                {message.status === 'unknown' && <p className="text-sm text-status-warning">{t('notifications.unknownHelp')}</p>}
                {message.is_manual && message.status === 'draft' && <p className="text-xs text-muted-foreground">{isChannel ? t('notifications.channelHelp') : t('notifications.manualHelp')}</p>}
                {message.sent_at !== null && <p className="text-xs text-muted-foreground">{formatDateTime(message.sent_at)}</p>}

                <div className="flex flex-wrap gap-2">
                    {dirty && message.can_edit && (
                        <Button size="sm" onClick={() => save.mutate()} disabled={save.isPending}>
                            {t('notifications.saveText')}
                        </Button>
                    )}
                    {message.can_send && (
                        <label className="flex items-center gap-2 rounded-md border px-2 py-1 text-sm">
                            <input type="checkbox" className="size-4 accent-primary" checked={selected} onChange={onToggle} disabled={dirty} />
                            {t('notifications.select', { label: message.destination.label })}
                        </label>
                    )}
                    {message.is_manual && (
                        <Button size="sm" variant="outline" onClick={() => void copy()}>
                            <Copy aria-hidden />
                            {t('notifications.copy')}
                        </Button>
                    )}
                    {message.is_manual && message.share_url !== null && (
                        <Button asChild size="sm" variant="outline">
                            <a href={message.share_url} target="_blank" rel="noreferrer">
                                <ExternalLink aria-hidden />
                                {isChannel ? t('notifications.openChannel') : t('notifications.openWhatsApp')}
                            </a>
                        </Button>
                    )}
                    {message.can_mark_sent && (
                        <Button size="sm" variant="outline" onClick={() => markSent.mutate()} disabled={markSent.isPending || dirty}>
                            <CheckCheck aria-hidden />
                            {t('notifications.markSent')}
                        </Button>
                    )}
                    {message.can_discard && (
                        <Button size="sm" variant="ghost" onClick={() => discard.mutate()} disabled={discard.isPending}>
                            <Trash2 aria-hidden />
                            {t('notifications.discard')}
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
