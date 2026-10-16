import { api } from './client';
import type { Category, GameFormat, Matchday, ReferenceData, Resource, Season, Team } from './types';

export interface CategoryInput {
    name: string;
    game_format: GameFormat;
    match_duration_min: number;
    post_min: number;
    min_gap_min: number;
    sort_order: number;
    is_active: boolean;
}

export interface TeamInput {
    category_id: number;
    name: string;
    game_format: GameFormat | null;
    color: string | null;
    match_duration_min: number | null;
    post_min: number | null;
    is_active: boolean;
}

export interface SeasonInput {
    name: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
}

export interface MatchdayInput {
    starts_on: string;
    ends_on: string;
    label: string | null;
}

export const referenceApi = {
    get: () => api.get<Resource<ReferenceData>>('/reference-data').then((r) => r.data),
};

export const categoriesApi = {
    list: () => api.get<Resource<Category[]>>('/categories').then((r) => r.data),
    create: (input: CategoryInput) => api.post<Resource<Category>>('/categories', input).then((r) => r.data),
    update: (id: number, input: CategoryInput) => api.put<Resource<Category>>(`/categories/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/categories/${id}`),
    markDurationsReviewed: () => api.post<void>('/categories/durations-reviewed'),
};

export const teamsApi = {
    list: () => api.get<Resource<Team[]>>('/teams').then((r) => r.data),
    create: (input: TeamInput) => api.post<Resource<Team>>('/teams', input).then((r) => r.data),
    update: (id: number, input: TeamInput) => api.put<Resource<Team>>(`/teams/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/teams/${id}`),
    reorder: (teamIds: number[]) => api.put<void>('/teams/order', { team_ids: teamIds }),
    uploadLogo: (id: number, file: File) => {
        const form = new FormData();
        form.append('logo', file);

        return api.post<Resource<Team>>(`/teams/${id}/logo`, form).then((r) => r.data);
    },
    deleteLogo: (id: number) => api.delete<Resource<Team>>(`/teams/${id}/logo`).then((r) => r.data),
};

export const seasonsApi = {
    list: () => api.get<Resource<Season[]>>('/seasons').then((r) => r.data),
    create: (input: SeasonInput) => api.post<Resource<Season>>('/seasons', input).then((r) => r.data),
    update: (id: number, input: SeasonInput) => api.put<Resource<Season>>(`/seasons/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/seasons/${id}`),
};

export const matchdaysApi = {
    list: (seasonId: number) => api.get<Resource<Matchday[]>>(`/seasons/${seasonId}/matchdays`).then((r) => r.data),
    create: (seasonId: number, input: MatchdayInput) => api.post<Resource<Matchday>>(`/seasons/${seasonId}/matchdays`, input).then((r) => r.data),
    update: (id: number, input: MatchdayInput) => api.put<Resource<Matchday>>(`/matchdays/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/matchdays/${id}`),
};
