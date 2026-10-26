import type { FetchHandler } from './helpers';

export const reference = {
    club_name: 'Joventut Sant Pere Màrtir',
    home_venue: { id: 1, name: 'Camp del Joventut Sant Pere Màrtir' },
    field_areas: [
        { id: 1, code: 'camp_1', name: 'Camp 1', kind: 'half' },
        { id: 2, code: 'camp_2', name: 'Camp 2', kind: 'half' },
        { id: 3, code: 'full', name: 'Camp complet', kind: 'full' },
    ],
    operating_hours: { first_kickoff: '09:00', last_kickoff: '21:00' },
    kickoff_step_min: 15,
    demo_durations_pending_review: false,
};

export function team(id: number, name: string, format: 'f7' | 'f11', category = 'Aleví') {
    return {
        id,
        name,
        slug: name.toLowerCase(),
        category_id: id,
        category: { id, name: category, game_format: format },
        game_format: null,
        match_duration_min: null,
        post_min: null,
        effective: { game_format: format, match_duration_min: format === 'f7' ? 60 : 105, post_min: 10 },
        color: null,
        preferred_schedule: null,
        inherited_preference: null,
        sort_order: id * 10,
        is_active: true,
        scheduling_rules: [],
    };
}

export const teams = [team(1, 'Aleví A', 'f7'), team(2, 'Primer equip', 'f11', 'Primer equip'), team(3, 'Cadet A', 'f11', 'Cadet')];

export const season = { id: 1, name: '2026-2027', starts_on: '2026-09-01', ends_on: '2027-06-30', is_current: true, matchdays_count: 1 };
export const matchday = { id: 7, season_id: 1, starts_on: '2026-10-17', ends_on: '2026-10-18', label: null, display_label: 'Jornada del 17/10/2026 al 18/10/2026', matches_count: 1 };

export const existingMatch = {
    id: 50,
    matchday_id: 7,
    team_id: 3,
    team: { id: 3, name: 'Cadet A', color: null, is_active: true, category: { id: 3, name: 'Cadet' } },
    round_label: null,
    opponent: 'UE Figueres',
    home_away: 'away',
    status: 'confirmed',
    game_format: 'f11',
    match_date: '2026-10-18',
    weekday: 7,
    kickoff_time: '10:00',
    call_time: '08:45',
    duration_min: 105,
    post_min: 10,
    end_time: null,
    occupation_end_time: null,
    field_area: null,
    field_area_id: null,
    venue_name: 'Vilatenim',
    address: null,
    municipality: 'Figueres',
    maps_url: null,
    is_locked: false,
    placement_source: null,
    rule_override_reason: null,
    notes: null,
    whatsapp_extra: null,
    show_in_image: true,
    score_team: null,
    score_opponent: null,
    has_result: false,
    lock_version: 0,
    updated_at: null,
};

/**
 * Servidor fals amb les dades d'una jornada. `extra` pot respondre o sobreescriure rutes.
 */
export function weekendServer(me: unknown, extra: FetchHandler = () => ({ status: 404 })): FetchHandler {
    return (url, init) => {
        const custom = extra(url, init);
        if (custom.status !== 404) return custom;

        const routes: Record<string, unknown> = {
            '/api/auth/me': { data: me },
            '/api/reference-data': { data: reference },
            '/api/seasons': { data: [season] },
            '/api/seasons/1/matchdays': { data: [matchday] },
            '/api/teams': { data: teams },
            '/api/categories': { data: [] },
            '/api/matchdays/7/matches': { data: [existingMatch] },
            '/api/matchdays/7/home-away-suggestions': {
                data: { '1': { suggestion: 'home', based_on: { matchday_id: 6, match_date: '2026-10-10', home_away: 'away' } }, '2': { suggestion: null, based_on: null } },
            },
        };

        return url in routes && (init.method ?? 'GET') === 'GET' ? { status: 200, body: routes[url] } : { status: 404 };
    };
}
