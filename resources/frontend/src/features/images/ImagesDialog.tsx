import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ImageIcon, Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { toast } from 'sonner';
import { imagesApi, type GeneratedImage, type ImageFormat } from '@/api/communications';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatDateTime, t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';
import { ImageGrid } from './ImageGrid';
import { IMAGE_HISTORY_KEY, imagesKey, useDeleteImages } from './useImages';

/**
 * Previsualització i descàrrega de les imatges (1080 × 1920) de la jornada confirmada.
 * Mentre es generen en segon pla, es consulta l'estat cada dos segons. Cada generació es conserva a l'històric.
 */
export function ImagesDialog({ scheduleId, open, onClose }: { scheduleId: number; open: boolean; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [grouped, setGrouped] = useState(false);
    const [format, setFormat] = useState<ImageFormat>('story');
    const [compress, setCompress] = useState(false);
    const [toDelete, setToDelete] = useState<GeneratedImage | 'batch' | null>(null);
    const state = useQuery({
        queryKey: imagesKey(scheduleId),
        queryFn: () => imagesApi.get(scheduleId),
        enabled: open,
        refetchInterval: (query) => (query.state.data?.generating === true ? 2000 : false),
    });
    const generate = useMutation({
        mutationFn: () => imagesApi.generate(scheduleId, { group_home_away: grouped, format, compress }),
        onSuccess: (data) => {
            queryClient.setQueryData(imagesKey(scheduleId), data);
            void queryClient.invalidateQueries({ queryKey: IMAGE_HISTORY_KEY });
            if (!data.generating) toast.success(t('images.generated'));
        },
        onError: (error) => toast.error(errorMessage(error)),
    });
    const { removeImage, removeBatch } = useDeleteImages(() => setToDelete(null));

    const images = state.data?.images ?? [];
    const generating = state.data?.generating === true || generate.isPending;
    const createdAt = images[0]?.created_at ?? null;

    return (
        <>
            <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
                <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>{t('images.title')}</DialogTitle>
                        <DialogDescription>{t('images.subtitle')}</DialogDescription>
                    </DialogHeader>

                    <fieldset className="rounded-lg border p-3">
                        <legend className="px-1 text-sm font-medium">{t('images.options')}</legend>
                        <div className="grid gap-3">
                            <div role="radiogroup" aria-label={t('images.format')} className="grid gap-2 sm:grid-cols-2">
                                {(['story', 'post'] as const).map((value) => (
                                    <label
                                        key={value}
                                        className="flex cursor-pointer items-center justify-center rounded-md border px-3 py-2 text-center text-sm has-checked:border-primary has-checked:bg-secondary has-checked:text-primary"
                                    >
                                        <input type="radio" name="image-format" className="sr-only" checked={format === value} onChange={() => setFormat(value)} />
                                        {t(`images.formats.${value}`)}
                                    </label>
                                ))}
                            </div>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={compress} onChange={(e) => setCompress(e.target.checked)} />
                                <span>
                                    {t('images.compress')}
                                    <span className="block text-xs text-muted-foreground">{t('images.compressHelp')}</span>
                                </span>
                            </label>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={grouped} onChange={(e) => setGrouped(e.target.checked)} />
                                <span>
                                    {t('images.groupHomeAway')}
                                    <span className="block text-xs text-muted-foreground">{t('images.groupHomeAwayHelp')}</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    {generating && (
                        <p className="flex items-center gap-2 text-sm text-primary" role="status">
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                            {t('images.generating')}
                        </p>
                    )}

                    {!generating && images.length === 0 && (
                        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-center text-sm text-muted-foreground">
                            <ImageIcon className="size-8" aria-hidden />
                            {t('images.none')}
                        </div>
                    )}

                    {images.some((image) => image.warnings.length > 0) && (
                        <ul className="grid gap-1 rounded-md bg-status-warning-soft p-3 text-sm text-status-warning">
                            {images.flatMap((image) =>
                                image.warnings.map((warning) => (
                                    <li key={`${image.id}-${warning}`} className="flex gap-2">
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                                        <span>
                                            <span className="capitalize">{image.weekday}</span>: {warning}
                                        </span>
                                    </li>
                                )),
                            )}
                        </ul>
                    )}

                    {images.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <span>{t('images.lastGeneration', { date: formatDateTime(createdAt) })}</span>
                            <Button variant="ghost" size="xs" onClick={() => setToDelete('batch')}>
                                {t('images.deleteBatch')}
                            </Button>
                        </div>
                    )}

                    <ImageGrid images={images} onDelete={(image) => setToDelete(image)} />

                    <p className="text-xs text-muted-foreground">
                        {t('images.historyHelp')}{' '}
                        <Link to="/imatges" className="font-medium text-primary underline underline-offset-2" onClick={onClose}>
                            {t('images.history')}
                        </Link>
                    </p>

                    <DialogFooter>
                        <Button variant="outline" onClick={onClose}>
                            {t('common.close')}
                        </Button>
                        <Button onClick={() => generate.mutate()} disabled={generating}>
                            <RefreshCw aria-hidden />
                            {images.length === 0 ? t('images.generate') : t('images.regenerate')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={toDelete !== null}
                title={toDelete === 'batch' ? t('images.deleteBatchTitle') : t('images.deleteImageTitle')}
                description={toDelete === 'batch' ? t('images.deleteBatchHelp') : t('images.deleteImageHelp')}
                confirmLabel={t('images.deleteImage')}
                pending={removeImage.isPending || removeBatch.isPending}
                onCancel={() => setToDelete(null)}
                onConfirm={() => {
                    if (toDelete === 'batch') removeBatch.mutate(images[0]?.batch_id ?? '');
                    else if (toDelete !== null) removeImage.mutate(toDelete.id);
                }}
            />
        </>
    );
}
