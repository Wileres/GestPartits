import { Download, Trash2 } from 'lucide-react';
import type { GeneratedImage } from '@/api/communications';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/ca';
import { formatWeekdayDate } from '@/lib/dates';

interface ImageGridProps {
    images: GeneratedImage[];
    /** Si s'indica, cada imatge té un botó per esborrar-la. */
    onDelete?: (image: GeneratedImage) => void;
}

/** Imatges generades (previsualització, descàrrega i, opcionalment, esborrat). */
export function ImageGrid({ images, onDelete }: ImageGridProps) {
    return (
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {images.map((image) => {
                const results = image.kind === 'results';
                const label = results ? t('images.resultsPreview') : t('images.preview', { day: formatWeekdayDate(image.day) });

                return (
                    <li key={image.id} className="grid gap-2">
                        <a href={image.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border shadow-sm">
                            <img src={image.url} alt={label} width={image.width} height={image.height} style={{ aspectRatio: `${image.width} / ${image.height}` }} className="w-full bg-muted object-cover" loading="lazy" />
                        </a>
                        <p className="text-xs font-medium capitalize">
                            {results ? t('images.kinds.results') : formatWeekdayDate(image.day)}
                            {image.total_pages > 1 && <span className="block font-normal text-muted-foreground normal-case">{t('images.page', { page: image.page, total: image.total_pages })}</span>}
                        </p>
                        <div className="flex gap-2">
                            <Button asChild variant="outline" size="sm" className="flex-1">
                                <a href={image.url} download={image.filename}>
                                    <Download aria-hidden />
                                    {t('images.download')}
                                </a>
                            </Button>
                            {onDelete !== undefined && (
                                <Button variant="outline" size="icon-sm" onClick={() => onDelete(image)} aria-label={t('images.deleteImageLabel', { day: results ? t('images.kinds.results') : formatWeekdayDate(image.day) })}>
                                    <Trash2 aria-hidden />
                                </Button>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
