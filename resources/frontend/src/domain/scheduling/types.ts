/**
 * Dades del motor de programació, en el mateix format JSON que App\Domain\Scheduling\Model\ScheduleInput
 * (camp «input» de la resposta de /api/matchdays/{id}/schedule i fixtures de tests/fixtures/scheduling).
 */
export type Area = 'camp_1' | 'camp_2' | 'full';

export interface InputMatch {
    id: number;
    team_id: number;
    team: string;
    category_id: number | null;
    category_order: number;
    team_order: number;
    format: 'f7' | 'f11';
    home_away: 'home' | 'away' | null;
    status: string;
    date: string | null;
    kickoff: string | null;
    area: Area | null;
    duration: number;
    post: number;
    gap: number;
    call: string | null;
    locked: boolean;
    override: string | null;
    venue: string | null;
}

export interface InputSlot {
    date: string;
    opens: string;
    last_kickoff: string;
    fill_order: number | null;
    label?: string;
}

export interface InputBlock {
    date: string;
    start: string;
    end: string;
    area: Area | null;
    reason: string;
}

export interface InputRule {
    id: number;
    team_id?: number | null;
    category_id?: number | null;
    format?: 'f7' | 'f11' | null;
    weekday?: number | null;
    preferred?: string | null;
    not_before?: string | null;
    not_after?: string | null;
    area?: Area | null;
    priority?: number;
    mandatory?: boolean;
    label?: string;
    valid_from?: string | null;
    valid_to?: string | null;
}

export interface ScheduleInput {
    dates: string[];
    step_min: number;
    operating: { first: string; last: string };
    slots: InputSlot[];
    blocks: InputBlock[];
    rules: InputRule[];
    matches: InputMatch[];
}

export type ConflictCode =
    | 'F7_CAPACITY_EXCEEDED'
    | 'F11_OVERLAPS_F7'
    | 'F11_OVERLAPS_F11'
    | 'AREA_OVERLAP'
    | 'F11_REQUIRES_FULL_FIELD'
    | 'F7_ON_FULL_FIELD'
    | 'OUTSIDE_OPERATING_HOURS'
    | 'OFF_GRID'
    | 'OUTSIDE_SLOT'
    | 'OUTSIDE_MATCHDAY'
    | 'FIELD_BLOCKED'
    | 'DUPLICATE_TEAM'
    | 'CALL_AFTER_KICKOFF'
    | 'MANDATORY_RULE_BROKEN'
    | 'RECOMMENDED_RULE_NOT_MET'
    | 'HOME_MATCH_UNSCHEDULED'
    | 'AWAY_MISSING_DATA';

export type Severity = 'critical' | 'warning';

export interface Conflict {
    code: ConflictCode;
    severity: Severity;
    match_ids: number[];
    params: Record<string, string | number | null>;
}
