import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { SchedulingRule } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/QueryState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useCategories } from '@/features/categories/useCategories';
import { useDeleteRule, useRules } from '@/features/slots/useSlots';
import { useTeams } from '@/features/teams/useTeams';
import { useReferenceData } from '@/hooks/useReferenceData';
import { t } from '@/i18n/ca';
import { formatDate } from '@/lib/dates';
import { errorMessage } from '@/lib/errors';
import { describeRule } from '@/lib/rules';
import { cn } from '@/lib/utils';
import { RuleFormDialog } from './RuleFormDialog';

function validity(rule: SchedulingRule): string {
    if (rule.valid_from === null && rule.valid_to === null) return t('rules.always');

    return `${rule.valid_from === null ? '…' : formatDate(rule.valid_from)} – ${rule.valid_to === null ? '…' : formatDate(rule.valid_to)}`;
}

export function RulesPage() {
    const { data: rules, isPending, isError } = useRules();
    const { data: teams = [] } = useTeams();
    const { data: categories = [] } = useCategories();
    const { data: reference } = useReferenceData();
    const deleteRule = useDeleteRule();
    const [form, setForm] = useState<{ open: boolean; rule: SchedulingRule | null }>({ open: false, rule: null });
    const [deleting, setDeleting] = useState<SchedulingRule | null>(null);

    return (
        <>
            <PageHeader
                title={t('rules.title')}
                description={t('rules.subtitle')}
                actions={
                    <Button onClick={() => setForm({ open: true, rule: null })}>
                        <Plus aria-hidden />
                        {t('rules.create')}
                    </Button>
                }
            />
            {isError && <ErrorState />}
            {isPending || reference === undefined ? (
                <LoadingState />
            ) : (
                <Card className="overflow-x-auto py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('rules.scope')}</TableHead>
                                <TableHead>{t('rules.preference')}</TableHead>
                                <TableHead>{t('rules.strength')}</TableHead>
                                <TableHead>{t('rules.priority')}</TableHead>
                                <TableHead className="hidden md:table-cell">{t('rules.validity')}</TableHead>
                                <TableHead className="text-right">{t('common.actions')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rules?.map((rule) => (
                                <TableRow key={rule.id} className={cn(!rule.is_active && 'opacity-60')}>
                                    <TableCell className="font-medium">
                                        {rule.scope_label}
                                        {rule.notes !== null && <span className="block text-xs font-normal text-muted-foreground">{rule.notes}</span>}
                                    </TableCell>
                                    <TableCell>{describeRule(rule, reference.field_areas)}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge
                                                variant="outline"
                                                className={cn('gap-1', rule.strength === 'mandatory' ? 'border-status-critical/40 bg-status-critical-soft text-status-critical' : '')}
                                            >
                                                {rule.strength === 'mandatory' && <ShieldCheck className="size-3" aria-hidden />}
                                                {t(`labels.strength.${rule.strength}`)}
                                            </Badge>
                                            {!rule.is_active && <Badge variant="outline">{t('rules.inactive')}</Badge>}
                                        </div>
                                    </TableCell>
                                    <TableCell>{rule.priority}</TableCell>
                                    <TableCell className="hidden md:table-cell">{validity(rule)}</TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <Button variant="ghost" size="icon" onClick={() => setForm({ open: true, rule })} aria-label={`${t('common.edit')}: ${rule.scope_label}`}>
                                            <Pencil aria-hidden />
                                        </Button>
                                        <Button variant="ghost" size="icon" onClick={() => setDeleting(rule)} aria-label={`${t('common.delete')}: ${rule.scope_label}`}>
                                            <Trash2 className="text-destructive" aria-hidden />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
            {reference !== undefined && (
                <RuleFormDialog
                    open={form.open}
                    rule={form.rule}
                    teams={teams}
                    categories={categories}
                    reference={reference}
                    onOpenChange={(open) => setForm((f) => ({ ...f, open }))}
                />
            )}
            <ConfirmDialog
                open={deleting !== null}
                title={t('rules.deleteConfirmTitle')}
                description={deleting?.scope_label}
                pending={deleteRule.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() =>
                    deleting !== null &&
                    deleteRule.mutate(deleting.id, {
                        onSuccess: () => {
                            toast.success(t('rules.deleted'));
                            setDeleting(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </>
    );
}
