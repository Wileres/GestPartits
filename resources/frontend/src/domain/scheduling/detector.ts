import type { Area, Conflict, ConflictCode, InputMatch, InputRule, ScheduleInput, Severity } from './types';

/**
 * Validador de conflictes del calendari: rèplica exacta de App\Domain\Scheduling\Conflicts\ConflictDetector.
 * Serveix per mostrar al moment si un destí és vàlid mentre s'arrossega; el servidor sempre torna a validar.
 * Totes dues implementacions passen les fixtures de tests/fixtures/scheduling.
 */

const ACTIVE = ['pending', 'proposed', 'confirmed'];
const HALVES: Area[] = ['camp_1', 'camp_2'];

export function minutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return (h ?? 0) * 60 + (m ?? 0);
}

function format(m: number): string {
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

function isoWeekday(date: string): number {
    const [y, mo, d] = date.split('-').map(Number);
    const day = new Date(Date.UTC(y ?? 1970, (mo ?? 1) - 1, d ?? 1)).getUTCDay();

    return day === 0 ? 7 : day;
}

export function resources(fmt: InputMatch['format'], area: Area | null): Area[] {
    return fmt === 'f11' || area === 'full' || area === null ? HALVES : [area];
}

const isActive = (m: InputMatch) => ACTIVE.includes(m.status);
const isPlaced = (m: InputMatch) => m.date !== null && m.kickoff !== null && m.area !== null;
const start = (m: InputMatch) => minutes(m.kickoff ?? '00:00');
const end = (m: InputMatch) => start(m) + m.duration + m.post;
const intersects = (a: Area[], b: Area[]) => a.some((x) => b.includes(x));
const compareIds = (a: number[], b: number[]) => {
    for (let i = 0; i < Math.max(a.length, b.length); i++) {
        const diff = (a[i] ?? -Infinity) - (b[i] ?? -Infinity);
        if (diff !== 0) return diff;
    }

    return 0;
};

function conflict(code: ConflictCode, severity: Severity, matchIds: number[], params: Conflict['params'] = {}): Conflict {
    return { code, severity, match_ids: matchIds, params };
}

/* Regles -------------------------------------------------------------------------------------------- */

function specificity(r: InputRule): number {
    if (r.team_id != null) return 3;
    if (r.category_id != null) return 2;

    return 1;
}

function applies(r: InputRule, m: InputMatch): boolean {
    if (r.team_id != null) return r.team_id === m.team_id;
    if (r.category_id != null) return r.category_id === m.category_id;
    if (r.format != null) return r.format === m.format;

    return false;
}

function validOn(r: InputRule, date: string): boolean {
    return (r.valid_from == null || date >= r.valid_from) && (r.valid_to == null || date <= r.valid_to);
}

export function preferenceFor(rules: InputRule[], m: InputMatch): InputRule | null {
    const candidates = rules.filter((r) => r.preferred != null && applies(r, m));
    candidates.sort((a, b) => specificity(b) - specificity(a) || (b.priority ?? 0) - (a.priority ?? 0) || a.id - b.id);

    return candidates[0] ?? null;
}

export function windowsFor(rules: InputRule[], m: InputMatch): InputRule[] {
    return rules.filter((r) => (r.not_before != null || r.not_after != null) && applies(r, m)).sort((a, b) => a.id - b.id);
}

function windowAllows(r: InputRule, kickoff: number): boolean {
    return (r.not_before == null || kickoff >= minutes(r.not_before)) && (r.not_after == null || kickoff <= minutes(r.not_after));
}

function preferenceMet(r: InputRule, m: InputMatch): boolean {
    return (r.weekday == null || isoWeekday(m.date ?? '') === r.weekday) && start(m) === minutes(r.preferred ?? '') && (r.area == null || r.area === m.area);
}

function ruleConflict(r: InputRule, m: InputMatch): Conflict {
    const params: Conflict['params'] = {
        rule_id: r.id,
        rule: r.label ?? '',
        weekday: r.weekday ?? null,
        preferred: r.preferred ?? null,
        not_before: r.not_before ?? null,
        not_after: r.not_after ?? null,
    };

    if (r.mandatory !== true) return conflict('RECOMMENDED_RULE_NOT_MET', 'warning', [m.id], params);

    return conflict('MANDATORY_RULE_BROKEN', m.override === null ? 'critical' : 'warning', [m.id], { ...params, override: m.override });
}

/* Detector ------------------------------------------------------------------------------------------ */

function overlaps(placed: InputMatch[]): Conflict[] {
    const result: Conflict[] = [];
    const capacitySets = new Map<string, number[]>();
    const f7 = placed.filter((m) => m.format === 'f7');

    for (const m of f7) {
        const simultaneous = f7.filter((o) => o.date === m.date && start(o) <= start(m) && start(m) < end(o));
        if (simultaneous.length >= 3) {
            const ids = simultaneous.map((o) => o.id).sort((a, b) => a - b);
            capacitySets.set(ids.join(','), ids);
        }
    }

    for (const ids of capacitySets.values()) result.push(conflict('F7_CAPACITY_EXCEEDED', 'critical', ids));

    const inSameSet = (a: number, b: number) => [...capacitySets.values()].some((ids) => ids.includes(a) && ids.includes(b));

    for (let i = 0; i < placed.length; i++) {
        for (let j = i + 1; j < placed.length; j++) {
            const a = placed[i] as InputMatch;
            const b = placed[j] as InputMatch;
            const gap = Math.max(a.gap, b.gap);
            const overlap = start(a) < end(b) + gap && start(b) < end(a) + gap;

            if (a.date !== b.date || !overlap || !intersects(resources(a.format, a.area), resources(b.format, b.area))) continue;

            const f11 = Number(a.format === 'f11') + Number(b.format === 'f11');
            if (f11 === 2) result.push(conflict('F11_OVERLAPS_F11', 'critical', [a.id, b.id]));
            else if (f11 === 1) result.push(conflict('F11_OVERLAPS_F7', 'critical', [a.id, b.id]));
            else if (!inSameSet(a.id, b.id)) result.push(conflict('AREA_OVERLAP', 'critical', [a.id, b.id]));
        }
    }

    return result;
}

function placementChecks(input: ScheduleInput, placed: InputMatch[]): Conflict[] {
    const result: Conflict[] = [];
    const first = minutes(input.operating.first);
    const last = minutes(input.operating.last);

    for (const m of placed) {
        const ids = [m.id];
        const kickoff = start(m);

        if (!input.dates.includes(m.date ?? '')) {
            result.push(conflict('OUTSIDE_MATCHDAY', 'critical', ids));
            continue;
        }

        if (m.format === 'f11' && m.area !== 'full') result.push(conflict('F11_REQUIRES_FULL_FIELD', 'critical', ids));
        if (m.format === 'f7' && m.area === 'full') result.push(conflict('F7_ON_FULL_FIELD', 'warning', ids));

        if (kickoff < first || kickoff > last) {
            result.push(conflict('OUTSIDE_OPERATING_HOURS', 'critical', ids, { from: input.operating.first, to: input.operating.last }));
        } else if (kickoff % input.step_min !== 0) {
            result.push(conflict('OFF_GRID', 'critical', ids, { step: input.step_min }));
        } else if (!input.slots.some((s) => s.date === m.date && kickoff >= minutes(s.opens) && kickoff <= minutes(s.last_kickoff))) {
            result.push(conflict('OUTSIDE_SLOT', 'warning', ids));
        }

        const res = resources(m.format, m.area);
        for (const block of input.blocks) {
            const blockEnd = block.end === '24:00' ? 1440 : minutes(block.end);
            const blockRes = block.area === null || block.area === 'full' ? HALVES : [block.area];
            if (block.date === m.date && kickoff < blockEnd && minutes(block.start) < end(m) && intersects(res, blockRes)) {
                result.push(conflict('FIELD_BLOCKED', 'critical', ids, { reason: block.reason }));
            }
        }
    }

    return result;
}

function ruleChecks(input: ScheduleInput, placed: InputMatch[]): Conflict[] {
    const reference = input.dates[0] ?? '1970-01-01';
    const rules = input.rules.filter((r) => validOn(r, reference));
    const result: Conflict[] = [];

    for (const m of placed) {
        const preference = preferenceFor(rules, m);
        if (preference !== null && !preferenceMet(preference, m)) result.push(ruleConflict(preference, m));

        for (const window of windowsFor(rules, m)) {
            if (!windowAllows(window, start(m))) result.push(ruleConflict(window, m));
        }
    }

    return result;
}

function matchChecks(active: InputMatch[], forConfirmation: boolean): Conflict[] {
    const result: Conflict[] = [];
    const byTeam = new Map<number, number[]>();

    for (const m of active) byTeam.set(m.team_id, [...(byTeam.get(m.team_id) ?? []), m.id]);
    for (const [teamId, ids] of byTeam) {
        if (ids.length > 1) result.push(conflict('DUPLICATE_TEAM', 'critical', ids, { team_id: teamId }));
    }

    for (const m of active) {
        if (m.kickoff !== null && m.call !== null && minutes(m.call) > minutes(m.kickoff)) result.push(conflict('CALL_AFTER_KICKOFF', 'critical', [m.id]));
        if (m.home_away === 'home' && !isPlaced(m)) result.push(conflict('HOME_MATCH_UNSCHEDULED', forConfirmation ? 'critical' : 'warning', [m.id]));
        if (m.home_away === 'away' && (m.date === null || m.kickoff === null || m.venue === null || m.venue === '')) {
            result.push(conflict('AWAY_MISSING_DATA', 'warning', [m.id]));
        }
    }

    return result;
}

export function detectConflicts(input: ScheduleInput, forConfirmation = false): Conflict[] {
    const active = input.matches.filter(isActive).sort((a, b) => a.id - b.id);
    const placedHome = active.filter((m) => m.home_away === 'home' && isPlaced(m));

    const conflicts = [...overlaps(placedHome), ...placementChecks(input, placedHome), ...ruleChecks(input, placedHome), ...matchChecks(active, forConfirmation)];

    const severityRank = (c: Conflict) => (c.severity === 'critical' ? 0 : 1);

    // Mateix ordre que el PHP: gravetat, codi (ordre de bytes) i ids.
    return conflicts.sort((a, b) => severityRank(a) - severityRank(b) || (a.code < b.code ? -1 : a.code > b.code ? 1 : 0) || compareIds(a.match_ids, b.match_ids));
}

/**
 * Conflictes d'un partit si es col·loqués a la posició indicada (null = sense programar).
 */
export function previewMove(input: ScheduleInput, matchId: number, target: { date: string; kickoff: string; area: Area } | null): Conflict[] {
    const matches = input.matches.map((m) =>
        m.id === matchId ? { ...m, date: target?.date ?? null, kickoff: target?.kickoff ?? null, area: target?.area ?? null } : m,
    );

    return detectConflicts({ ...input, matches }).filter((c) => c.match_ids.includes(matchId));
}

export function worstSeverity(conflicts: Conflict[]): Severity | 'valid' {
    if (conflicts.some((c) => c.severity === 'critical')) return 'critical';
    if (conflicts.length > 0) return 'warning';

    return 'valid';
}

export { format as formatMinutes };
