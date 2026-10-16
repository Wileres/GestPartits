import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download, ImageIcon, Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';
import { imagesApi } from '@/api/communications';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/i18n/ca';
import { formatWeekdayDate } from '@/lib/dates';
import { errorMessage } from '@/lib/errors';

const imagesKey = (scheduleId: number) => ['schedules', scheduleId, 'images'] as const;

/**
 * Previsualització i descàrrega de les imatges (1080 × 1920) de la jornada confirmada.
 * Mentre es generen en segon pla, es consulta l'estat cada dos segons.
 */
export function ImagesDialog({ scheduleId, open, onClose }: { scheduleId: number; open: boolean; onClose: () => void }) {
    const queryClient = useQueryClient();
    const state = useQuery({
        queryKey: imagesKey(scheduleId),
        queryFn: () => imagesApi.get(scheduleId),
        enabled: open,
        refetchInterval: (query) => (query.state.data?.generating === true ? 2000 : false),
    });
    const generate = useMutation({
        mutationFn: () => imagesApi.generate(scheduleId),
        onSuccess: (data) => {
            queryClient.setQueryData(imagesKey(scheduleId), data);
            if (!data.generating) toast.success(t('images.generated'));
        },
        onError: (error) => toast.error(errorMessage(error)),
    });

    const images = state.data?.images ?? [];
    const generating = state.data?.generating === true || generate.isPending;

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>{t('images.title')}</DialogTitle>
                    <DialogDescription>{t('images.subtitle')}</DialogDescription>
                </DialogHeader>

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

                <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {images.map((image) => (
                        <li key={image.id} className="grid gap-2">
                            <a href={image.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border shadow-sm">
                                <img src={image.url} alt={t('images.preview', { day: formatWeekdayDate(image.day) })} className="aspect-[9/16] w-full bg-muted object-cover" loading="lazy" />
                            </a>
                            <p className="text-xs font-medium capitalize">
                                {formatWeekdayDate(image.day)}
                                {image.total_pages > 1 && <span className="block font-normal text-muted-foreground normal-case">{t('images.page', { page: image.page, total: image.total_pages })}</span>}
                            </p>
                            <Button asChild variant="outline" size="sm">
                                <a href={image.url} download={image.filename}>
                                    <Download aria-hidden />
                                    {t('images.download')}
                                </a>
                            </Button>
                        </li>
                    ))}
                </ul>

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
    );
}
