import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { imageHistoryApi } from '@/api/communications';
import { t } from '@/i18n/ca';
import { errorMessage } from '@/lib/errors';

export const imagesKey = (scheduleId: number) => ['schedules', scheduleId, 'images'] as const;
export const resultImagesKey = (matchdayId: number) => ['matchdays', matchdayId, 'result-images'] as const;
export const IMAGE_HISTORY_KEY = ['image-history'] as const;

/** Esborra una imatge o una generació sencera i refresca tot el que en depèn. */
export function useDeleteImages(onDone?: () => void) {
    const queryClient = useQueryClient();
    const refresh = () => {
        void queryClient.invalidateQueries({ queryKey: IMAGE_HISTORY_KEY });
        void queryClient.invalidateQueries({ queryKey: ['schedules'] });
        void queryClient.invalidateQueries({ queryKey: ['matchdays'] });
    };
    const options = {
        onSuccess: () => {
            toast.success(t('images.deleted'));
            refresh();
            onDone?.();
        },
        onError: (error: unknown) => toast.error(errorMessage(error)),
    };

    return {
        removeImage: useMutation({ mutationFn: (id: number) => imageHistoryApi.remove(id), ...options }),
        removeBatch: useMutation({ mutationFn: (batchId: string) => imageHistoryApi.removeBatch(batchId), ...options }),
    };
}
