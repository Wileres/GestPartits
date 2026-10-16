import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authApi, type LoginInput } from '@/api/auth';
import { ApiError } from '@/api/client';
import type { Me } from '@/api/types';

export const meQueryKey = ['auth', 'me'] as const;

/**
 * Usuari autenticat (null si no hi ha sessió).
 */
export function useMe() {
    return useQuery<Me | null>({
        queryKey: meQueryKey,
        queryFn: async () => {
            try {
                return await authApi.me();
            } catch (error) {
                if (error instanceof ApiError && error.status === 401) {
                    return null;
                }
                throw error;
            }
        },
        staleTime: 5 * 60 * 1000,
        retry: false,
    });
}

export function useLogin() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: LoginInput) => authApi.login(input),
        onSuccess: (me) => {
            queryClient.clear();
            queryClient.setQueryData(meQueryKey, me);
        },
    });
}

export function useLogout() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => authApi.logout(),
        onSettled: () => {
            queryClient.clear();
            queryClient.setQueryData(meQueryKey, null);
        },
    });
}
