import { api } from './client';
import type { AvailabilitySlot, FieldBlock, GameFormat, Resource, RuleStrength, SchedulingRule } from './types';

export interface AvailabilitySlotInput {
    weekday: number | null;
    specific_date: string | null;
    label: string;
    opens_at: string;
    last_kickoff_at: string;
    default_fill_order: number | null;
    is_active: boolean;
}

export interface FieldBlockInput {
    field_area_id: number | null;
    starts_at: string;
    ends_at: string;
    reason: string;
}

export interface SchedulingRuleInput {
    team_id: number | null;
    category_id: number | null;
    game_format: GameFormat | null;
    weekday: number | null;
    preferred_time: string | null;
    not_before: string | null;
    not_after: string | null;
    preferred_field_area_id: number | null;
    priority: number;
    strength: RuleStrength;
    valid_from: string | null;
    valid_to: string | null;
    is_active: boolean;
    notes: string | null;
}

export const slotsApi = {
    list: () => api.get<Resource<AvailabilitySlot[]>>('/availability-slots').then((r) => r.data),
    create: (input: AvailabilitySlotInput) => api.post<Resource<AvailabilitySlot>>('/availability-slots', input).then((r) => r.data),
    update: (id: number, input: AvailabilitySlotInput) => api.put<Resource<AvailabilitySlot>>(`/availability-slots/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/availability-slots/${id}`),
};

export const blocksApi = {
    list: () => api.get<Resource<FieldBlock[]>>('/field-blocks').then((r) => r.data),
    create: (input: FieldBlockInput) => api.post<Resource<FieldBlock>>('/field-blocks', input).then((r) => r.data),
    update: (id: number, input: FieldBlockInput) => api.put<Resource<FieldBlock>>(`/field-blocks/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/field-blocks/${id}`),
};

export const rulesApi = {
    list: () => api.get<Resource<SchedulingRule[]>>('/scheduling-rules').then((r) => r.data),
    create: (input: SchedulingRuleInput) => api.post<Resource<SchedulingRule>>('/scheduling-rules', input).then((r) => r.data),
    update: (id: number, input: SchedulingRuleInput) => api.put<Resource<SchedulingRule>>(`/scheduling-rules/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/scheduling-rules/${id}`),
};
