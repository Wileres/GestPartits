import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { detectConflicts, previewMove } from '@/domain/scheduling/detector';
import type { ScheduleInput } from '@/domain/scheduling/types';

const dir = join(__dirname, '../../../tests/fixtures/scheduling');

interface Fixture {
    description: string;
    for_confirmation: boolean;
    input: ScheduleInput;
    expected: { code: string; severity: string; match_ids: number[] }[];
}

const fixtures = readdirSync(dir)
    .filter((file) => file.endsWith('.json'))
    .sort()
    .map((file) => [file, JSON.parse(readFileSync(join(dir, file), 'utf8')) as Fixture] as const);

describe('validador del calendari (mateixes fixtures que el PHP)', () => {
    it('troba les fixtures', () => {
        expect(fixtures.length).toBeGreaterThanOrEqual(18);
    });

    it.each(fixtures)('%s', (_file, fixture) => {
        const conflicts = detectConflicts(fixture.input, fixture.for_confirmation).map(({ code, severity, match_ids }) => ({ code, severity, match_ids }));

        expect(conflicts, fixture.description).toEqual(fixture.expected);
    });

    it('previsualitza un moviment: el tercer futbol 7 alhora és crític', () => {
        const fixture = fixtures.find(([file]) => file.startsWith('01-'))?.[1];
        expect(fixture).toBeDefined();
        const input = { ...(fixture as Fixture).input, matches: [...(fixture as Fixture).input.matches, { ...(fixture as Fixture).input.matches[0]!, id: 9, team_id: 999, date: null, kickoff: null, area: null }] };

        expect(previewMove(input, 9, { date: '2026-10-17', kickoff: '10:30', area: 'camp_1' }).map((c) => c.code)).toEqual(['F7_CAPACITY_EXCEEDED']);
        expect(previewMove(input, 9, { date: '2026-10-17', kickoff: '11:15', area: 'camp_1' })).toEqual([]);
    });
});
