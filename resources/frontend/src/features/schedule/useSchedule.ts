import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ApiError } from '@/api/client';
import { scheduleApi, type MoveTarget, type ScheduleData } from '@/api/schedule';
import { errorMessage } from '@/lib/errors';

export const scheduleKey = (matchdayId: number) => ['matchdays', matchdayId, 'schedule'] as const;

export function useSchedule(matchdayId: number | null) {
    return useQuery({
        queryKey: scheduleKey(matchdayId ?? 0),
        queryFn: () => scheduleApi.get(matchdayId ?? 0),
        enabled: matchdayId !== null,
    });
}

/**
 * Mutació sobre la programació. La resposta és l'estat complet, que substitueix el de la memòria cau.
 * Si la versió ha quedat antiga (409), es recarrega i s'avisa. Els 423 (conflictes en confirmar) els
 * gestiona qui crida la mutació.
 */
function useScheduleMutation<V>(matchdayId: number, schedule: ScheduleData | undefined, fn: (id: number, version: number, variables: V) => Promise<ScheduleData>) {
    const queryClient = useQueryClient();
    const key = scheduleKey(matchdayId);

    return useMutation({
        mutationFn: (variables: V) => {
            if (schedule === undefined) throw new Error('Programació no carregada');

            return fn(schedule.id, schedule.version, variables);
        },
        onSuccess: (data) => {
            queryClient.setQueryData(key, data);
            void queryClient.invalidateQueries({ queryKey: ['matchdays', matchdayId, 'matches'] });
        },
        onError: (error) => {
            if (error instanceof ApiError && error.status === 409) {
                void queryClient.invalidateQueries({ queryKey: key });
            }
            if (!(error instanceof ApiError && error.status === 423)) {
                toast.error(errorMessage(error), { id: 'schedule-error' });
            }
        },
    });
}

export function useScheduleActions(matchdayId: number, schedule: ScheduleData | undefined) {
    return {
        propose: useScheduleMutation<void>(matchdayId, schedule, (id, version) => scheduleApi.propose(id, version)),
        save: useScheduleMutation<void>(matchdayId, schedule, (id, version) => scheduleApi.save(id, version)),
        undo: useScheduleMutation<void>(matchdayId, schedule, (id, version) => scheduleApi.undo(id, version)),
        confirm: useScheduleMutation<void>(matchdayId, schedule, (id, version) => scheduleApi.confirm(id, version)),
        move: useScheduleMutation<{ matchId: number; target: MoveTarget }>(matchdayId, schedule, (id, version, { matchId, target }) =>
            scheduleApi.move(id, version, matchId, target),
        ),
        toggleLock: useScheduleMutation<{ matchId: number; locked: boolean }>(matchdayId, schedule, (id, version, { matchId, locked }) =>
            locked ? scheduleApi.lock(id, version, matchId) : scheduleApi.unlock(id, version, matchId),
        ),
    };
}
