import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { usersApi, type CreateUserInput, type UpdateUserInput } from '@/api/users';

const usersKey = ['users'] as const;

export function useUsers() {
    return useQuery({ queryKey: usersKey, queryFn: usersApi.list });
}

export function useCreateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: CreateUserInput) => usersApi.create(input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: usersKey }),
    });
}

export function useUpdateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, input }: { id: number; input: UpdateUserInput }) => usersApi.update(id, input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: usersKey }),
    });
}

export function useDeleteUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => usersApi.remove(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: usersKey }),
    });
}
