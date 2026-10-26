import { useQuery } from '@tanstack/react-query';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { imageHistoryApi, type ImageBatch, type ImageKind } from '@/api/communications';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDateTime, t } from '@/i18n/ca';
import { cn } from '@/lib/utils';
import { ImageGrid } from './ImageGrid';
import { IMAGE_HISTORY_KEY, useDeleteImages } from './useImages';

const filters: { value: ImageKind | null; label: string }[] = [
    { value: null, label: t('images.filterAll') },
    { value: 'schedule', label: t('images.kinds.schedule') },
    { value: 'results', label: t('images.kinds.results') },
];

/** Històric de totes les imatges generades: es poden baixar i esborrar a mà. */
export function ImagesHistoryPage() {
    const [kind, setKind] = useState<ImageKind | null>(null);
    const [toDelete, setToDelete] = useState<{ batch: ImageBatch; imageId: number | null } | null>(null);
    const history = useQuery({ queryKey: [...IMAGE_HISTORY_KEY, kind], queryFn: () => imageHistoryApi.list(kind) });
    const { removeImage, removeBatch } = useDeleteImages(() => setToDelete(null));

    return (
        <>
            <PageHeader title={t('images.historyTitle')} description={t('images.historySubtitle')} />

            <div className="mb-6 flex flex-wrap gap-2" role="group" aria-label={t('images.historyTitle')}>
                {filters.map((filter) => (
                    <Button key={filter.label} variant={kind === filter.value ? 'default' : 'outline'} size="sm" aria-pressed={kind === filter.value} onClick={() => setKind(filter.value)}>
                        {filter.label}
                    </Button>
                ))}
            </div>

            {history.isPending ? (
                <LoadingState />
            ) : history.isError ? (
                <ErrorState />
            ) : history.data.length === 0 ? (
                <EmptyState title={t('images.historyEmpty')} />
            ) : (
                <ul className="grid gap-6">
                    {history.data.map((batch) => (
                        <li key={batch.batch_id}>
                            <Card className={cn('gap-4 px-4 py-4')}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="grid gap-1">
                                        <p className="flex flex-wrap items-center gap-2 font-semibold">
                                            <Badge variant={batch.kind === 'results' ? 'default' : 'secondary'}>{t(`images.kinds.${batch.kind}`)}</Badge>
                                            {batch.matchday?.display_label}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {batch.created_by === null ? t('images.generatedOn', { date: formatDateTime(batch.created_at) }) : t('images.generatedBy', { name: batch.created_by, date: formatDateTime(batch.created_at) })}
                                            {batch.kind === 'schedule' && ` · ${batch.options?.group_home_away === true ? t('images.optionGrouped') : t('images.optionPlain')}`}
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={() => setToDelete({ batch, imageId: null })}>
                                        <Trash2 aria-hidden />
                                        {t('images.deleteBatch')}
                                    </Button>
                                </div>
                                <ImageGrid images={batch.images} onDelete={(image) => setToDelete({ batch, imageId: image.id })} />
                            </Card>
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmDialog
                open={toDelete !== null}
                title={toDelete?.imageId === null ? t('images.deleteBatchTitle') : t('images.deleteImageTitle')}
                description={toDelete?.imageId === null ? t('images.deleteBatchHelp') : t('images.deleteImageHelp')}
                confirmLabel={t('images.deleteImage')}
                pending={removeImage.isPending || removeBatch.isPending}
                onCancel={() => setToDelete(null)}
                onConfirm={() => {
                    if (toDelete === null) return;
                    if (toDelete.imageId === null) removeBatch.mutate(toDelete.batch.batch_id);
                    else removeImage.mutate(toDelete.imageId);
                }}
            />
        </>
    );
}
