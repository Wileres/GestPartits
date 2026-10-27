import type { Conflict, ScheduleInput } from '@/domain/scheduling/types';
import { api } from './client';
import type { Match, Matchday, MatchStatus } from './types';

export type ScheduleStatus = 'draft' | 'proposed' | 'confirmed' | 'needs_review';

export interface PresentedConflict extends Conflict {
    message: string;
}

export interface ScheduleVersionSummary {
    id: number;
    number: number;
    kind: 'pre_proposal' | 'proposal' | 'manual_save' | 'confirmation' | 'undo';
    kind_label: string;
    summary: string | null;
    created_by: string | null;
    created_at: string | null;
    is_current: boolean;
}

export interface PlacementSummary {
    match_date: string | null;
    kickoff_time: string | null;
    field_area: string | null;
    status: MatchStatus;
    is_locked: boolean;
}

export interface VersionChange {
    match_id: number;
    team: string | null;
    opponent: string | null;
    from: PlacementSummary | null;
    to: PlacementSummary | null;
}

export interface ProposalSummary {
    placed: number[];
    unplaced: Record<string, { reason: 'no_slot' | 'field_full'; message: string }>;
    relocated: number[];
    changes: VersionChange[];
}

export interface ScheduleData {
    id: number;
    status: ScheduleStatus;
    status_label: string;
    version: number;
    confirmed_at: string | null;
    confirmed_by: string | null;
    has_unsaved_changes: boolean;
    can_confirm: boolean;
    matchday: Matchday;
    matches: Match[];
    conflicts: PresentedConflict[];
    blocking_conflicts: PresentedConflict[];
    input: ScheduleInput;
    versions: ScheduleVersionSummary[];
    proposal: ProposalSummary | null;
}

export interface MoveTarget {
    match_date: string | null;
    kickoff_time: string | null;
    field_area_id: number | null;
}

interface Wrapped<T> {
    data: T;
}

export const scheduleApi = {
    get: (matchdayId: number) => api.get<Wrapped<ScheduleData>>(`/matchdays/${matchdayId}/schedule`).then((r) => r.data),
    propose: (id: number, version: number) => api.post<Wrapped<ScheduleData>>(`/schedules/${id}/proposal`, { version }).then((r) => r.data),
    save: (id: number, version: number) => api.post<Wrapped<ScheduleData>>(`/schedules/${id}/save`, { version }).then((r) => r.data),
    undo: (id: number, version: number) => api.post<Wrapped<ScheduleData>>(`/schedules/${id}/undo`, { version }).then((r) => r.data),
    restore: (id: number, version: number, versionId: number) => api.post<Wrapped<ScheduleData>>(`/schedules/${id}/versions/${versionId}/restore`, { version }).then((r) => r.data),
    confirm: (id: number, version: number) => api.post<Wrapped<ScheduleData>>(`/schedules/${id}/confirm`, { version }).then((r) => r.data),
    move: (id: number, version: number, matchId: number, target: MoveTarget) =>
        api.post<Wrapped<ScheduleData>>(`/schedules/${id}/moves`, { version, match_id: matchId, ...target }).then((r) => r.data),
    validateMove: (id: number, matchId: number, target: MoveTarget) =>
        api.post<Wrapped<{ valid: boolean; conflicts: PresentedConflict[] }>>(`/schedules/${id}/moves/validate`, { match_id: matchId, ...target }).then((r) => r.data),
    lock: (id: number, version: number, matchId: number) => api.post<Wrapped<ScheduleData>>(`/schedules/${id}/matches/${matchId}/lock`, { version }).then((r) => r.data),
    unlock: (id: number, version: number, matchId: number) =>
        api.request<Wrapped<ScheduleData>>('DELETE', `/schedules/${id}/matches/${matchId}/lock`, { version }).then((r) => r.data),
    compare: (id: number, from: number, to: number) => api.get<Wrapped<VersionChange[]>>(`/schedules/${id}/versions/${from}/compare/${to}`).then((r) => r.data),
};
