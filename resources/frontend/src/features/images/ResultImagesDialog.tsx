import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ImageIcon, Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { toast } from 'sonner';
import { resultImagesApi, type GeneratedImage } from '@/api/communications';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatDateTime, t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';
import { ImageGrid } from './ImageGrid';
import { IMAGE_HISTORY_KEY, resultImagesKey, useDeleteImages } from './useImages';

/**
 * Imatge amb el resum dels resultats de la jornada (una sola imatge sempre que hi càpiguen).
 * Es genera en segon pla i es conserva a l'històric.
 */
export function ResultImagesDialog({ matchdayId, open, onClose }: { matchdayId: number; open: boolean; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [toDelete, setToDelete] = useState<GeneratedImage | null>(null);
    const state = useQuery({
        queryKey: resultImagesKey(matchdayId),
        queryFn: () => resultImagesApi.get(matchdayId),
        enabled: open,
        refetchInterval: (query) => (query.state.data?.generating === true ? 2000 : false),
    });
    const generate = useMutation({
        mutationFn: () => resultImagesApi.generate(matchdayId),
        onSuccess: (data) => {
            queryClient.setQueryData(resultImagesKey(matchdayId), data);
            void queryClient.invalidateQueries({ queryKey: IMAGE_HISTORY_KEY });
            if (!data.generating) toast.success(t('images.generated'));
        },
        onError: (error) => toast.error(errorMessage(error)),
    });
    const { removeImage } = useDeleteImages(() => setToDelete(null));

    const images = state.data?.images ?? [];
    const count = state.data?.results_count ?? 0;
    const generating = state.data?.generating === true || generate.isPending;

    return (
        <>
            <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
                <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{t('images.results')}</DialogTitle>
                        <DialogDescription>{t('images.resultsSubtitle')}</DialogDescription>
                    </DialogHeader>

                    {state.isSuccess && count === 0 && (
                        <p className="flex items-start gap-2 rounded-md bg-status-warning-soft p-3 text-sm text-status-warning">
                            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                            {t('images.resultsNone')}
                        </p>
                    )}
                    {count > 0 && <p className="text-sm">{count === 1 ? t('images.resultsCountOne') : t('images.resultsCount', { count })}</p>}

                    {generating && (
                        <p className="flex items-center gap-2 text-sm text-primary" role="status">
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                            {t('images.generating')}
                        </p>
                    )}

                    {!generating && images.length === 0 && count > 0 && (
                        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-center text-sm text-muted-foreground">
                            <ImageIcon className="size-8" aria-hidden />
                            {t('images.resultsNoneYet')}
                        </div>
                    )}

                    {images.some((image) => image.warnings.length > 0) && (
                        <ul className="grid gap-1 rounded-md bg-status-warning-soft p-3 text-sm text-status-warning">
                            {images.flatMap((image) =>
                                image.warnings.map((warning) => (
                                    <li key={`${image.id}-${warning}`} className="flex gap-2">
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                                        {warning}
                                    </li>
                                )),
                            )}
                        </ul>
                    )}

                    {images.length > 0 && <p className="text-xs text-muted-foreground">{t('images.lastGeneration', { date: formatDateTime(images[0]?.created_at ?? null) })}</p>}
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
                        <Button onClick={() => generate.mutate()} disabled={generating || count === 0}>
                            <RefreshCw aria-hidden />
                            {images.length === 0 ? t('images.resultsGenerate') : t('images.resultsRegenerate')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={toDelete !== null}
                title={t('images.deleteImageTitle')}
                description={t('images.deleteImageHelp')}
                confirmLabel={t('images.deleteImage')}
                pending={removeImage.isPending}
                onCancel={() => setToDelete(null)}
                onConfirm={() => toDelete !== null && removeImage.mutate(toDelete.id)}
            />
        </>
    );
}
