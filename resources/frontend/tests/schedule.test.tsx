import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { existingMatch, matchday, reference, season, teams } from './fixtures';
import { adminMe, mockFetch, renderApp, type FetchHandler } from './helpers';

afterEach(() => vi.unstubAllGlobals());

const base = { ...existingMatch, home_away: 'home', status: 'proposed', venue_name: null, municipality: null, call_time: null, end_time: null, occupation_end_time: null };

const homeAlevi = { ...base, id: 61, team_id: 1, team: { id: 1, name: 'Aleví A', color: null, is_active: true, category: { id: 1, name: 'Aleví' } }, game_format: 'f7', match_date: '2026-10-17', weekday: 6, kickoff_time: '11:15', duration_min: 60, post_min: 10, field_area_id: 1, field_area: reference.field_areas[0] };
const homePrimer = { ...base, id: 62, team_id: 2, team: { id: 2, name: 'Primer equip', color: null, is_active: true, category: { id: 2, name: 'Primer equip' } }, game_format: 'f11', match_date: null, weekday: null, kickoff_time: null, duration_min: 105, post_min: 10, field_area_id: null, field_area: null, status: 'confirmed' };

const input = {
    dates: ['2026-10-17', '2026-10-18'],
    step_min: 15,
    operating: { first: '09:00', last: '21:00' },
    slots: [
        { date: '2026-10-17', opens: '09:00', last_kickoff: '13:00', fill_order: 1, label: 'Dissabte matí' },
        { date: '2026-10-18', opens: '09:00', last_kickoff: '13:00', fill_order: 2, label: 'Diumenge matí' },
    ],
    blocks: [{ date: '2026-10-18', start: '09:00', end: '10:00', area: null, reason: 'Manteniment' }],
    rules: [],
    matches: [
        { id: 61, team_id: 1, team: 'Aleví A', category_id: 1, category_order: 0, team_order: 1, format: 'f7', home_away: 'home', status: 'proposed', date: '2026-10-17', kickoff: '11:15', area: 'camp_1', duration: 60, post: 10, gap: 0, call: null, locked: false, override: null, venue: null },
        { id: 62, team_id: 2, team: 'Primer equip', category_id: 2, category_order: 0, team_order: 2, format: 'f11', home_away: 'home', status: 'confirmed', date: null, kickoff: null, area: null, duration: 105, post: 10, gap: 0, call: null, locked: false, override: null, venue: null },
        { id: 50, team_id: 3, team: 'Cadet A', category_id: 3, category_order: 0, team_order: 3, format: 'f11', home_away: 'away', status: 'confirmed', date: '2026-10-18', kickoff: '10:00', area: null, duration: 105, post: 10, gap: 0, call: '08:45', locked: false, override: null, venue: 'Vilatenim' },
    ],
};

const blocking = [{ code: 'HOME_MATCH_UNSCHEDULED', severity: 'critical', match_ids: [62], params: {}, message: 'Primer equip: partit a casa sense horari.' }];

const schedule = {
    id: 3,
    status: 'proposed',
    status_label: 'Proposta',
    version: 4,
    confirmed_at: null,
    confirmed_by: null,
    has_unsaved_changes: false,
    can_confirm: false,
    matchday,
    matches: [homeAlevi, homePrimer, existingMatch],
    conflicts: [{ ...blocking[0], severity: 'warning' }],
    blocking_conflicts: blocking,
    input,
    versions: [{ id: 1, number: 1, kind: 'proposal', kind_label: 'Proposta automàtica', summary: 'Proposta', created_by: 'Gestor', created_at: null, is_current: true }],
    proposal: null,
};

function server(extra: FetchHandler = () => ({ status: 404 })): FetchHandler {
    return (url, init) => {
        const custom = extra(url, init);
        if (custom.status !== 404) return custom;
        const routes: Record<string, unknown> = {
            '/api/auth/me': { data: adminMe },
            '/api/reference-data': { data: reference },
            '/api/seasons': { data: [season] },
            '/api/seasons/1/matchdays': { data: [matchday] },
            '/api/teams': { data: teams },
            '/api/matchdays/7/schedule': { data: schedule },
        };

        return url in routes ? { status: 200, body: routes[url] } : { status: 404 };
    };
}

describe('calendari de la jornada', () => {
    it('mostra dissabte i diumenge amb els partits, la safata, els partits a fora i els conflictes', async () => {
        mockFetch(server());

        renderApp('/');

        expect(await screen.findByRole('region', { name: 'Calendari de dissabte 17/10/2026' })).toBeInTheDocument();
        expect(screen.getByRole('region', { name: 'Calendari de diumenge 18/10/2026' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Edita Aleví A' })).toHaveTextContent('11:15–12:15');
        expect(screen.getByText('Manteniment')).toBeInTheDocument();

        const tray = screen.getByRole('heading', { name: /Partits sense programar/ }).closest('[data-slot=card]') as HTMLElement;
        expect(within(tray).getByText('Primer equip')).toBeInTheDocument();
        expect(screen.getByText('Vilatenim · Figueres')).toBeInTheDocument();
        expect(screen.getByText('Primer equip: partit a casa sense horari.')).toBeInTheDocument();
        expect(screen.getByText('Proposta')).toBeInTheDocument();
    });

    it('«Mou a…» valida el destí al moment i envia el moviment', async () => {
        const { calls } = mockFetch(server((url, init) => (url === '/api/schedules/3/moves' && init.method === 'POST' ? { status: 200, body: { data: schedule } } : { status: 404 })));
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Mou a… Primer equip' }));
        const dialog = await screen.findByRole('dialog');

        // Diumenge a les 09:00: el camp està bloquejat.
        await user.selectOptions(within(dialog).getByLabelText('Dia'), '2026-10-18');
        await user.selectOptions(within(dialog).getByLabelText('Hora'), '09:00');
        expect(within(dialog).getByText('Destí amb conflictes')).toBeInTheDocument();
        expect(within(dialog).getByText('Camp bloquejat')).toBeInTheDocument();
        // El futbol 11 només pot anar al camp complet.
        expect(within(within(dialog).getByLabelText('Camp')).getAllByRole('option').map((o) => o.textContent)).toEqual(['Camp complet']);

        await user.selectOptions(within(dialog).getByLabelText('Hora'), '10:30');
        expect(within(dialog).getByText('Destí vàlid')).toBeInTheDocument();
        await user.click(within(dialog).getByRole('button', { name: 'Mou el partit' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/schedules/3/moves')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.url === '/api/schedules/3/moves')?.init.body)) as unknown;
        expect(body).toEqual({ version: 4, match_id: 62, match_date: '2026-10-18', kickoff_time: '10:30', field_area_id: 3 });
    });

    it('no deixa confirmar si hi ha conflictes crítics i explica per què', async () => {
        const { calls } = mockFetch(server());
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Confirma la programació' }));
        const dialog = await screen.findByRole('dialog');

        expect(within(dialog).getByText('No es pot confirmar mentre hi hagi conflictes crítics:')).toBeInTheDocument();
        expect(within(dialog).getByText('Primer equip: partit a casa sense horari.')).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Confirma la programació' })).toBeDisabled();
        expect(calls.some((c) => c.url.endsWith('/confirm'))).toBe(false);
    });

    it('genera una proposta i en mostra els canvis', async () => {
        const proposal = {
            placed: [62],
            unplaced: {},
            relocated: [],
            changes: [{ match_id: 62, team: 'Primer equip', opponent: 'CF Olot', from: null, to: { match_date: '2026-10-17', kickoff_time: '17:00', field_area: 'Camp complet', status: 'proposed', is_locked: false } }],
        };
        const { calls } = mockFetch(server((url, init) => (url === '/api/schedules/3/proposal' && init.method === 'POST' ? { status: 200, body: { data: { ...schedule, version: 5, proposal } } } : { status: 404 })));
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Genera una proposta' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText('Proposta generada')).toBeInTheDocument();
        expect(within(dialog).getByText('dissabte 17/10/2026 17:00 · Camp complet')).toBeInTheDocument();
        expect(JSON.parse(String(calls.find((c) => c.url === '/api/schedules/3/proposal')?.init.body))).toEqual({ version: 4 });
    });

    it('recarrega la programació si ha canviat mentrestant (409)', async () => {
        let scheduleRequests = 0;
        mockFetch(
            server((url, init) => {
                if (url === '/api/matchdays/7/schedule') {
                    scheduleRequests++;

                    return { status: 200, body: { data: schedule } };
                }
                if (url === '/api/schedules/3/save' && init.method === 'POST') return { status: 409, body: { message: 'La programació ha canviat mentrestant.' } };

                return { status: 404 };
            }),
        );
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Desa la programació' }));

        await waitFor(() => expect(scheduleRequests).toBeGreaterThanOrEqual(2));
    });
});
