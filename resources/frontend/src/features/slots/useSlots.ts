import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { blocksApi, rulesApi, slotsApi, type AvailabilitySlotInput, type FieldBlockInput, type SchedulingRuleInput } from '@/api/scheduling';

export const slotsKey = ['availability-slots'] as const;
export const blocksKey = ['field-blocks'] as const;
export const rulesKey = ['scheduling-rules'] as const;

export function useSlots() {
    return useQuery({ queryKey: slotsKey, queryFn: slotsApi.list });
}

export function useBlocks() {
    return useQuery({ queryKey: blocksKey, queryFn: blocksApi.list });
}

export function useRules() {
    return useQuery({ queryKey: rulesKey, queryFn: rulesApi.list });
}

export function useSaveSlot() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: AvailabilitySlotInput }) => (id === null ? slotsApi.create(input) : slotsApi.update(id, input)),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: slotsKey }),
    });
}

export function useDeleteSlot() {
    const queryClient = useQueryClient();

    return useMutation({ mutationFn: (id: number) => slotsApi.remove(id), onSuccess: () => queryClient.invalidateQueries({ queryKey: slotsKey }) });
}

export function useSaveBlock() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: FieldBlockInput }) => (id === null ? blocksApi.create(input) : blocksApi.update(id, input)),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: blocksKey }),
    });
}

export function useDeleteBlock() {
    const queryClient = useQueryClient();

    return useMutation({ mutationFn: (id: number) => blocksApi.remove(id), onSuccess: () => queryClient.invalidateQueries({ queryKey: blocksKey }) });
}

export function useSaveRule() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: SchedulingRuleInput }) => (id === null ? rulesApi.create(input) : rulesApi.update(id, input)),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: rulesKey });
            void queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });
}

export function useDeleteRule() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => rulesApi.remove(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: rulesKey });
            void queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });
}
