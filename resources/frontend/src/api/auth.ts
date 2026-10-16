import { api } from './client';
import type { Me, MessageResponse, Resource } from './types';

export interface LoginInput {
    username: string;
    password: string;
    remember: boolean;
}

export interface ChangePasswordInput {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export interface ResetPasswordInput {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export const authApi = {
    me: () => api.get<Resource<Me>>('/auth/me').then((r) => r.data),
    login: (input: LoginInput) => api.post<Resource<Me>>('/auth/login', input).then((r) => r.data),
    logout: () => api.post<void>('/auth/logout'),
    changePassword: (input: ChangePasswordInput) => api.post<Resource<Me>>('/auth/password/change', input).then((r) => r.data),
    forgotPassword: (email: string) => api.post<MessageResponse>('/auth/password/forgot', { email }),
    resetPassword: (input: ResetPasswordInput) => api.post<MessageResponse>('/auth/password/reset', input),
};
