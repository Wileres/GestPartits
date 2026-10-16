import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { matchdaysApi, seasonsApi, type MatchdayInput, type SeasonInput } from '@/api/catalog';

export const seasonsKey = ['seasons'] as const;
export const matchdaysKey = (seasonId: number) => ['seasons', seasonId, 'matchdays'] as const;

export function useSeasons() {
    return useQuery({ queryKey: seasonsKey, queryFn: seasonsApi.list });
}

export function useMatchdays(seasonId: number | null) {
    return useQuery({
        queryKey: matchdaysKey(seasonId ?? 0),
        queryFn: () => matchdaysApi.list(seasonId ?? 0),
        enabled: seasonId !== null,
    });
}

export function useSaveSeason() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: SeasonInput }) => (id === null ? seasonsApi.create(input) : seasonsApi.update(id, input)),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: seasonsKey }),
    });
}

export function useDeleteSeason() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => seasonsApi.remove(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: seasonsKey }),
    });
}

export function useSaveMatchday(seasonId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: MatchdayInput }) => (id === null ? matchdaysApi.create(seasonId, input) : matchdaysApi.update(id, input)),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: matchdaysKey(seasonId) });
            void queryClient.invalidateQueries({ queryKey: seasonsKey });
        },
    });
}

export function useDeleteMatchday(seasonId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => matchdaysApi.remove(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: matchdaysKey(seasonId) });
            void queryClient.invalidateQueries({ queryKey: seasonsKey });
        },
    });
}
