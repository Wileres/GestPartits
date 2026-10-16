import { Pencil, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { Category } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { ErrorState, LoadingState } from '@/components/QueryState';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useReferenceData } from '@/hooks/useReferenceData';
import { t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';
import { CategoryFormDialog } from './CategoryFormDialog';
import { useCategories, useDeleteCategory, useMarkDurationsReviewed } from './useCategories';

export function CategoriesPage() {
    const { data: categories, isPending, isError } = useCategories();
    const { data: reference } = useReferenceData();
    const markReviewed = useMarkDurationsReviewed();
    const deleteCategory = useDeleteCategory();
    const [editing, setEditing] = useState<Category | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Category | null>(null);

    const open = (category: Category | null) => {
        setEditing(category);
        setFormOpen(true);
    };

    return (
        <>
            <PageHeader
                title={t('categories.title')}
                description={t('categories.subtitle')}
                actions={
                    <Button onClick={() => open(null)}>
                        <Plus aria-hidden />
                        {t('categories.create')}
                    </Button>
                }
            />

            {reference?.demo_durations_pending_review === true && (
                <Alert className="mb-4 border-status-warning/40 bg-status-warning-soft text-status-warning">
                    <TriangleAlert aria-hidden />
                    <AlertTitle>{t('categories.demoTitle')}</AlertTitle>
                    <AlertDescription className="flex flex-wrap items-center justify-between gap-2 text-status-warning">
                        {t('categories.demoHelp')}
                        <Button size="sm" variant="outline" onClick={() => markReviewed.mutate()} disabled={markReviewed.isPending}>
                            {t('categories.demoReviewed')}
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            {isError && <ErrorState />}
            {isPending ? (
                <LoadingState />
            ) : (
                <Card className="overflow-x-auto py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('categories.name')}</TableHead>
                                <TableHead>{t('categories.format')}</TableHead>
                                <TableHead>{t('categories.duration')}</TableHead>
                                <TableHead>{t('categories.post')}</TableHead>
                                <TableHead className="hidden sm:table-cell">{t('categories.teams')}</TableHead>
                                <TableHead className="text-right">{t('common.actions')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories?.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell className="font-medium">
                                        {category.name}
                                        {!category.is_active && (
                                            <Badge variant="outline" className="ml-2">
                                                {t('common.inactive')}
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>{t(`labels.format.${category.game_format}`)}</TableCell>
                                    <TableCell>{t('common.minutes', { value: category.match_duration_min })}</TableCell>
                                    <TableCell>{t('common.minutes', { value: category.post_min })}</TableCell>
                                    <TableCell className="hidden sm:table-cell">{category.teams_count ?? 0}</TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="ghost" size="icon" onClick={() => open(category)} aria-label={`${t('common.edit')}: ${category.name}`}>
                                            <Pencil aria-hidden />
                                        </Button>
                                        <Button variant="ghost" size="icon" onClick={() => setDeleting(category)} aria-label={`${t('common.delete')}: ${category.name}`}>
                                            <Trash2 className="text-destructive" aria-hidden />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <CategoryFormDialog open={formOpen} category={editing} onOpenChange={setFormOpen} />
            <ConfirmDialog
                open={deleting !== null}
                title={t('categories.deleteConfirmTitle', { name: deleting?.name ?? '' })}
                pending={deleteCategory.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() =>
                    deleting !== null &&
                    deleteCategory.mutate(deleting.id, {
                        onSuccess: () => {
                            toast.success(t('categories.deleted'));
                            setDeleting(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </>
    );
}
