import { useQuery } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { api } from '@/api/client';
import { DateTextInput } from '@/components/DateTextInput';
import { PageHeader } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/QueryState';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, t } from '@/i18n/ca';
import { displayToIso } from '@/lib/dateSchema';

interface AuditEntry {
    id: number;
    event: string;
    user: string | null;
    username: string | null;
    subject: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip: string | null;
    created_at: string | null;
}

interface Page {
    data: AuditEntry[];
    meta: { current_page: number; last_page: number; total: number };
}

const EVENT_PREFIXES = ['auth', 'user', 'schedule', 'match', 'team', 'category', 'scheduling_rule', 'availability_slot', 'field_block', 'whatsapp', 'images', 'settings'] as const;

function values(entry: AuditEntry): string {
    const show = (v: Record<string, unknown> | null) => (v === null ? '' : JSON.stringify(v));
    const before = show(entry.old_values);
    const after = show(entry.new_values);

    return [before !== '' ? `${t('audit.before')}: ${before}` : '', after !== '' ? `${t('audit.after')}: ${after}` : ''].filter(Boolean).join(' · ');
}

export function AuditPage() {
    const [filters, setFilters] = useState({ event: '', from: '', to: '' });
    const [applied, setApplied] = useState(filters);
    const [page, setPage] = useState(1);

    const query = useQuery({
        queryKey: ['audit-logs', applied, page],
        queryFn: () => {
            const params = new URLSearchParams({ page: String(page) });
            if (applied.event !== '') params.set('event', applied.event);
            const from = displayToIso(applied.from);
            const to = displayToIso(applied.to);
            if (from !== null) params.set('from', from);
            if (to !== null) params.set('to', to);

            return api.get<Page>(`/audit-logs?${params.toString()}`);
        },
    });

    return (
        <>
            <PageHeader title={t('audit.title')} description={t('audit.subtitle')} />
            <form
                className="mb-4 flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    setPage(1);
                    setApplied(filters);
                }}
            >
                <div className="grid gap-1.5">
                    <Label htmlFor="audit-event">{t('audit.event')}</Label>
                    <Input id="audit-event" list="audit-events" value={filters.event} onChange={(e) => setFilters((f) => ({ ...f, event: e.target.value }))} className="w-56" />
                    <datalist id="audit-events">
                        {EVENT_PREFIXES.map((prefix) => (
                            <option key={prefix} value={prefix} />
                        ))}
                    </datalist>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="audit-from">{t('audit.from')}</Label>
                    <DateTextInput id="audit-from" value={filters.from} onChange={(e) => setFilters((f) => ({ ...f, from: e.target.value }))} className="w-36" />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="audit-to">{t('audit.to')}</Label>
                    <DateTextInput id="audit-to" value={filters.to} onChange={(e) => setFilters((f) => ({ ...f, to: e.target.value }))} className="w-36" />
                </div>
                <Button type="submit">{t('audit.filter')}</Button>
            </form>

            {query.isPending ? (
                <LoadingState />
            ) : query.isError ? (
                <ErrorState />
            ) : (
                <>
                    <Card className="overflow-x-auto py-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('audit.when')}</TableHead>
                                    <TableHead>{t('audit.event')}</TableHead>
                                    <TableHead>{t('audit.user')}</TableHead>
                                    <TableHead>{t('audit.subject')}</TableHead>
                                    <TableHead className="min-w-80">{t('audit.details')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {query.data.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="whitespace-nowrap tabular-nums">{formatDateTime(entry.created_at)}</TableCell>
                                        <TableCell className="font-mono text-xs">{entry.event}</TableCell>
                                        <TableCell>{entry.user ?? t('audit.system')}</TableCell>
                                        <TableCell className="text-xs">{entry.subject ?? '—'}</TableCell>
                                        <TableCell className="max-w-md truncate font-mono text-xs" title={values(entry)}>
                                            {values(entry)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                    <div className="mt-3 flex items-center justify-end gap-2 text-sm">
                        <span>{t('audit.page', { page: query.data.meta.current_page, total: query.data.meta.last_page, count: query.data.meta.total })}</span>
                        <Button variant="outline" size="icon" onClick={() => setPage((p) => p - 1)} disabled={page <= 1} aria-label={t('audit.previous')}>
                            <ChevronLeft aria-hidden />
                        </Button>
                        <Button variant="outline" size="icon" onClick={() => setPage((p) => p + 1)} disabled={page >= query.data.meta.last_page} aria-label={t('audit.next')}>
                            <ChevronRight aria-hidden />
                        </Button>
                    </div>
                </>
            )}
        </>
    );
}
