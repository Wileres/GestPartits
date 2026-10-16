import { api } from './client';
import type { Resource, RoleCode, User } from './types';

export interface CreateUserInput {
    username: string;
    name: string;
    email: string | null;
    role: RoleCode;
    password: string;
}

export interface UpdateUserInput {
    username?: string;
    name?: string;
    email?: string | null;
    role?: RoleCode;
    is_active?: boolean;
    password?: string | null;
}

export const usersApi = {
    list: () => api.get<Resource<User[]>>('/users').then((r) => r.data),
    create: (input: CreateUserInput) => api.post<Resource<User>>('/users', input).then((r) => r.data),
    update: (id: number, input: UpdateUserInput) => api.patch<Resource<User>>(`/users/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/users/${id}`),
};
