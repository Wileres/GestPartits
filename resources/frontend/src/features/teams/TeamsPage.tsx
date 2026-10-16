import { closestCenter, DndContext, KeyboardSensor, PointerSensor, TouchSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { FieldArea, Team } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useCategories } from '@/features/categories/useCategories';
import { useReferenceData } from '@/hooks/useReferenceData';
import { t } from '@/i18n/ca';
import { catalanAnnouncements } from '@/lib/dnd';
import { errorMessage } from '@/lib/errors';
import { describeRule } from '@/lib/rules';
import { cn } from '@/lib/utils';
import { TeamFormDialog } from './TeamFormDialog';
import { useDeleteTeam, useReorderTeams, useTeams } from './useTeams';

export function TeamsPage() {
    const { data: teams, isPending, isError } = useTeams();
    const { data: categories = [] } = useCategories();
    const { data: reference } = useReferenceData();
    const reorder = useReorderTeams();
    const deleteTeam = useDeleteTeam();
    const [editing, setEditing] = useState<Team | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Team | null>(null);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (teams === undefined || over === null || active.id === over.id) return;

        const from = teams.findIndex((team) => team.id === active.id);
        const to = teams.findIndex((team) => team.id === over.id);
        reorder.mutate(arrayMove(teams, from, to), {
            onSuccess: () => toast.success(t('teams.reordered')),
            onError: (error) => toast.error(errorMessage(error)),
        });
    };

    const open = (team: Team | null) => {
        setEditing(team);
        setFormOpen(true);
    };

    return (
        <>
            <PageHeader
                title={t('teams.title')}
                description={t('teams.subtitle')}
                actions={
                    <Button onClick={() => open(null)} disabled={categories.length === 0}>
                        <Plus aria-hidden />
                        {t('teams.create')}
                    </Button>
                }
            />
            {isError && <ErrorState />}
            {isPending ? (
                <LoadingState />
            ) : teams === undefined || teams.length === 0 ? (
                <EmptyState title={t('teams.create')} />
            ) : (
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={onDragEnd}
                    accessibility={catalanAnnouncements((id) => teams.find((team) => team.id === id)?.name ?? String(id))}
                >
                    <SortableContext items={teams.map((team) => team.id)} strategy={verticalListSortingStrategy}>
                        <ul className="grid gap-2">
                            {teams.map((team) => (
                                <SortableTeamRow key={team.id} team={team} areas={reference?.field_areas ?? []} onEdit={() => open(team)} onDelete={() => setDeleting(team)} />
                            ))}
                        </ul>
                    </SortableContext>
                </DndContext>
            )}

            <TeamFormDialog open={formOpen} team={editing} categories={categories} onOpenChange={setFormOpen} />
            <ConfirmDialog
                open={deleting !== null}
                title={t('teams.deleteConfirmTitle', { name: deleting?.name ?? '' })}
                description={t('teams.deleteConfirmHelp')}
                pending={deleteTeam.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() =>
                    deleting !== null &&
                    deleteTeam.mutate(deleting.id, {
                        onSuccess: () => {
                            toast.success(t('teams.deleted'));
                            setDeleting(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </>
    );
}

interface RowProps {
    team: Team;
    areas: FieldArea[];
    onEdit: () => void;
    onDelete: () => void;
}

function SortableTeamRow({ team, areas, onEdit, onDelete }: RowProps) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: team.id });
    const rules = team.scheduling_rules.filter((rule) => rule.is_active);

    return (
        <li ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} className={cn(isDragging && 'relative z-10')}>
            <Card className={cn('flex-row items-center gap-3 px-3 py-3', isDragging && 'shadow-lg ring-2 ring-primary/30', !team.is_active && 'opacity-60')}>
                <button
                    ref={setActivatorNodeRef}
                    type="button"
                    className="flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
                    aria-label={t('teams.reorderHandle', { name: team.name })}
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" aria-hidden />
                </button>
                {team.logo_url !== null ? (
                    <img src={team.logo_url} alt="" className="size-9 shrink-0 rounded border bg-white object-contain" />
                ) : (
                    <span className="size-9 shrink-0 rounded-full border-2 border-white shadow" style={{ backgroundColor: team.color ?? 'var(--secondary)' }} aria-hidden />
                )}
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 font-medium">
                        {team.name}
                        {!team.is_active && <Badge variant="outline">{t('common.inactive')}</Badge>}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {team.category.name} · {t(`labels.format.${team.effective.game_format}`)} · {t('common.minutes', { value: team.effective.match_duration_min })}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('teams.preferredSchedule')}: {rules.length === 0 ? t('teams.noPreferredSchedule') : rules.map((rule) => describeRule(rule, areas)).join(' · ')}
                    </p>
                </div>
                <div className="flex shrink-0 gap-1">
                    <Button variant="ghost" size="icon" onClick={onEdit} aria-label={`${t('common.edit')}: ${team.name}`}>
                        <Pencil aria-hidden />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={onDelete} aria-label={`${t('common.delete')}: ${team.name}`}>
                        <Trash2 className="text-destructive" aria-hidden />
                    </Button>
                </div>
            </Card>
        </li>
    );
}
