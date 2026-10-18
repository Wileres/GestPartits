import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { teamsApi, type TeamInput } from '@/api/catalog';
import type { Team } from '@/api/types';

export const teamsKey = ['teams'] as const;

export function useTeams() {
    return useQuery({ queryKey: teamsKey, queryFn: teamsApi.list });
}

export function useSaveTeam() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number | null; input: TeamInput }) => (id === null ? teamsApi.create(input) : teamsApi.update(id, input)),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: teamsKey });
            void queryClient.invalidateQueries({ queryKey: ['categories'] });
        },
    });
}

export function useDeleteTeam() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => teamsApi.remove(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: teamsKey }),
    });
}

/**
 * Desa el nou ordre. La llista es reordena a l'instant i es restaura si el servidor falla.
 */
export function useReorderTeams() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (teams: Team[]) => teamsApi.reorder(teams.map((team) => team.id)),
        onMutate: async (teams) => {
            await queryClient.cancelQueries({ queryKey: teamsKey });
            const previous = queryClient.getQueryData<Team[]>(teamsKey);
            queryClient.setQueryData(teamsKey, teams);

            return { previous };
        },
        onError: (_error, _teams, context) => {
            if (context?.previous !== undefined) {
                queryClient.setQueryData(teamsKey, context.previous);
            }
        },
        onSettled: () => queryClient.invalidateQueries({ queryKey: teamsKey }),
    });
}
