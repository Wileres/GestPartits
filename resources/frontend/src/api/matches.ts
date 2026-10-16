import { api } from './client';
import type { GameFormat, HomeAway, HomeAwaySuggestion, Match, MatchStatus, Resource } from './types';

export interface MatchInput {
    team_id: number;
    status: MatchStatus;
    home_away: HomeAway | null;
    opponent: string | null;
    round_label: string | null;
    game_format: GameFormat | null;
    match_date: string | null;
    kickoff_time: string | null;
    call_time: string | null;
    duration_min: number | null;
    post_min: number | null;
    field_area_id: number | null;
    venue_name: string | null;
    address: string | null;
    municipality: string | null;
    maps_url: string | null;
    is_locked: boolean;
    rule_override_reason: string | null;
    notes: string | null;
    whatsapp_extra: string | null;
    show_in_image: boolean;
}

export interface QuickEntryRow {
    team_id: number;
    status: MatchStatus;
    home_away: HomeAway | null;
    opponent: string | null;
    round_label: string | null;
}

export const matchesApi = {
    list: (matchdayId: number) => api.get<Resource<Match[]>>(`/matchdays/${matchdayId}/matches`).then((r) => r.data),
    create: (matchdayId: number, input: MatchInput) => api.post<Resource<Match>>(`/matchdays/${matchdayId}/matches`, input).then((r) => r.data),
    createMany: (matchdayId: number, rows: QuickEntryRow[]) => api.post<Resource<Match[]>>(`/matchdays/${matchdayId}/matches/bulk`, { rows }).then((r) => r.data),
    update: (id: number, input: MatchInput & { lock_version: number }) => api.put<Resource<Match>>(`/matches/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/matches/${id}`),
    suggestions: (matchdayId: number) =>
        api.get<Resource<Record<string, HomeAwaySuggestion>>>(`/matchdays/${matchdayId}/home-away-suggestions`).then((r) => r.data),
};
