import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { matchesApi, type MatchInput, type QuickEntryRow } from '@/api/matches';

export const matchesKey = (matchdayId: number) => ['matchdays', matchdayId, 'matches'] as const;

export function useMatches(matchdayId: number | null) {
    return useQuery({ queryKey: matchesKey(matchdayId ?? 0), queryFn: () => matchesApi.list(matchdayId ?? 0), enabled: matchdayId !== null });
}

export function useHomeAwaySuggestions(matchdayId: number | null) {
    return useQuery({
        queryKey: ['matchdays', matchdayId, 'suggestions'],
        queryFn: () => matchesApi.suggestions(matchdayId ?? 0),
        enabled: matchdayId !== null,
    });
}

function useInvalidateMatchday(matchdayId: number) {
    const queryClient = useQueryClient();

    return () => {
        void queryClient.invalidateQueries({ queryKey: ['matchdays', matchdayId] });
        void queryClient.invalidateQueries({ queryKey: ['seasons'] });
    };
}

export function useCreateMatches(matchdayId: number) {
    const invalidate = useInvalidateMatchday(matchdayId);

    return useMutation({ mutationFn: (rows: QuickEntryRow[]) => matchesApi.createMany(matchdayId, rows), onSuccess: invalidate });
}

export function useSaveMatch(matchdayId: number) {
    const invalidate = useInvalidateMatchday(matchdayId);

    return useMutation({
        mutationFn: ({ id, lockVersion, input }: { id: number | null; lockVersion: number; input: MatchInput }) =>
            id === null ? matchesApi.create(matchdayId, input) : matchesApi.update(id, { ...input, lock_version: lockVersion }),
        // També en cas d'error (409): es recarreguen les dades actuals.
        onSettled: invalidate,
    });
}

export function useDeleteMatch(matchdayId: number) {
    const invalidate = useInvalidateMatchday(matchdayId);

    return useMutation({ mutationFn: (id: number) => matchesApi.remove(id), onSuccess: invalidate });
}
