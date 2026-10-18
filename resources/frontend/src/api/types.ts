export type RoleCode = 'admin' | 'manager';

export interface Me {
    id: number;
    username: string;
    name: string;
    email: string | null;
    role: RoleCode;
    role_label: string;
    must_change_password: boolean;
    permissions: {
        manage_users: boolean;
        manage_settings: boolean;
        view_audit: boolean;
    };
}

export interface User {
    id: number;
    username: string;
    name: string;
    email: string | null;
    role: RoleCode;
    role_label: string;
    is_active: boolean;
    must_change_password: boolean;
    last_login_at: string | null;
    created_at: string | null;
}

export interface Resource<T> {
    data: T;
}

export interface MessageResponse {
    message: string;
}

export type GameFormat = 'f7' | 'f11';
export type HomeAway = 'home' | 'away';
export type MatchStatus = 'proposed' | 'confirmed' | 'cancelled';
export type RuleStrength = 'mandatory' | 'recommended';
export type FieldAreaKind = 'half' | 'full';

export interface FieldArea {
    id: number;
    code: string;
    name: string;
    kind: FieldAreaKind;
}

export interface ReferenceData {
    club_name: string;
    home_venue: { id: number; name: string };
    field_areas: FieldArea[];
    operating_hours: { first_kickoff: string; last_kickoff: string };
    kickoff_step_min: number;
    demo_durations_pending_review: boolean;
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    game_format: GameFormat;
    match_duration_min: number;
    post_min: number;
    min_gap_min: number;
    sort_order: number;
    is_active: boolean;
    teams_count?: number;
}

export interface PreferredSchedule {
    weekday: number | null;
    time: string | null;
    field_area_id: number | null;
}

export interface SchedulingRule {
    id: number;
    team_id: number | null;
    category_id: number | null;
    game_format: GameFormat | null;
    scope_label: string;
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

export interface Team {
    id: number;
    name: string;
    slug: string;
    category_id: number;
    category: { id: number; name: string; game_format: GameFormat };
    game_format: GameFormat | null;
    match_duration_min: number | null;
    post_min: number | null;
    effective: { game_format: GameFormat; match_duration_min: number; post_min: number };
    color: string | null;
    /** Horari preferent a casa propi de l'equip (editable des de la seva fitxa). */
    preferred_schedule: PreferredSchedule | null;
    /** Si no en té de propi, el de la categoria o la modalitat. */
    inherited_preference: (PreferredSchedule & { source: 'category' | 'format' }) | null;
    sort_order: number;
    is_active: boolean;
    scheduling_rules: SchedulingRule[];
}

export interface Season {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
    matchdays_count?: number;
}

export interface Matchday {
    id: number;
    season_id: number;
    starts_on: string;
    ends_on: string;
    label: string | null;
    display_label: string;
    matches_count?: number;
}

export interface Match {
    id: number;
    matchday_id: number;
    team_id: number;
    team: { id: number; name: string; color: string | null; is_active: boolean; category: { id: number; name: string } | null };
    round_label: string | null;
    opponent: string | null;
    home_away: HomeAway | null;
    status: MatchStatus;
    game_format: GameFormat;
    match_date: string | null;
    weekday: number | null;
    kickoff_time: string | null;
    call_time: string | null;
    duration_min: number;
    post_min: number;
    end_time: string | null;
    occupation_end_time: string | null;
    field_area: FieldArea | null;
    field_area_id: number | null;
    venue_name: string | null;
    address: string | null;
    municipality: string | null;
    maps_url: string | null;
    is_locked: boolean;
    placement_source: 'auto' | 'manual' | null;
    rule_override_reason: string | null;
    notes: string | null;
    whatsapp_extra: string | null;
    show_in_image: boolean;
    lock_version: number;
    updated_at: string | null;
}

export interface HomeAwaySuggestion {
    suggestion: HomeAway | null;
    based_on: { matchday_id: number; match_date: string | null; home_away: HomeAway } | null;
}

export interface AvailabilitySlot {
    id: number;
    weekday: number | null;
    specific_date: string | null;
    label: string;
    opens_at: string;
    last_kickoff_at: string;
    default_fill_order: number | null;
    is_active: boolean;
}

export interface FieldBlock {
    id: number;
    field_area_id: number | null;
    field_area: FieldArea | null;
    starts_at: string;
    ends_at: string;
    reason: string;
}
