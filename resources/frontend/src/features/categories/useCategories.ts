import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { categoriesApi, type CategoryInput } from '@/api/catalog';
import { referenceKey } from '@/hooks/useReferenceData';

export const categoriesKey = ['categories'] as const;

export function useCategories() {
    return useQuery({ queryKey: categoriesKey, queryFn: categoriesApi.list });
}

export function useSaveCategory() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: CategoryInput }) => (id === null ? categoriesApi.create(input) : categoriesApi.update(id, input)),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: categoriesKey });
            // Els equips mostren els valors heretats de la categoria.
            void queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });
}

export function useDeleteCategory() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => categoriesApi.remove(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: categoriesKey }),
    });
}

export function useMarkDurationsReviewed() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: categoriesApi.markDurationsReviewed,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: referenceKey }),
    });
}
