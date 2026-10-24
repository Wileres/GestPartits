import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { existingMatch, weekendServer } from './fixtures';
import { adminMe, mockFetch, renderApp } from './helpers';

afterEach(() => vi.unstubAllGlobals());

describe('full ràpid de partits', () => {
    it('proposa casa o fora a partir de la jornada anterior i crea els partits', async () => {
        const { calls } = mockFetch(
            weekendServer(adminMe, (url, init) =>
                url === '/api/matchdays/7/matches/bulk' && init.method === 'POST' ? { status: 200, body: { data: [{}, {}] } } : { status: 404 },
            ),
        );

        renderApp('/partits');

        const condition = await screen.findByLabelText('Condició · Aleví A');
        // Suggeriment: l'últim partit va ser a fora, ara toca a casa.
        expect(condition).toHaveValue('home');
        expect(screen.getByText('Suggeriment: Casa')).toBeInTheDocument();

        const user = userEvent.setup();
        await user.type(screen.getByLabelText('Rival · Aleví A'), 'CF Olot');
        await user.selectOptions(screen.getByLabelText('Condició · Primer equip'), 'away');
        await user.type(screen.getByLabelText('Rival · Primer equip'), 'UE Llagostera');
        // Un partit a fora porta el dia i l'hora directament a la fila; el de casa no en porta.
        expect(screen.queryByLabelText('Hora del partit · Aleví A')).not.toBeInTheDocument();
        await user.selectOptions(screen.getByLabelText('Data · Primer equip'), '2026-10-18');
        await user.type(screen.getByLabelText('Hora del partit · Primer equip'), '10:10');

        await user.click(screen.getByRole('button', { name: 'Crea els partits (2)' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/matchdays/7/matches/bulk')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.url === '/api/matchdays/7/matches/bulk')?.init.body)) as { rows: unknown[] };
        // Els partits nous no porten estat: el servidor els desa com a confirmats.
        expect(body.rows).toEqual([
            { team_id: 1, home_away: 'home', opponent: 'CF Olot', round_label: null, match_date: null, kickoff_time: null },
            { team_id: 2, home_away: 'away', opponent: 'UE Llagostera', round_label: null, match_date: '2026-10-18', kickoff_time: '10:10' },
        ]);
    });

    it('no deixa crear un partit a fora amb una hora invàlida o sense dia', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/partits');

        const user = userEvent.setup();
        await user.selectOptions(await screen.findByLabelText('Condició · Primer equip'), 'away');
        const create = screen.getByRole('button', { name: /Crea els partits/ });
        await user.type(screen.getByLabelText('Hora del partit · Primer equip'), '25:99');
        expect(create).toBeDisabled();

        await user.clear(screen.getByLabelText('Hora del partit · Primer equip'));
        await user.type(screen.getByLabelText('Hora del partit · Primer equip'), '10:10');
        // Falta el dia.
        expect(create).toBeDisabled();

        await user.selectOptions(screen.getByLabelText('Data · Primer equip'), '2026-10-17');
        expect(create).toBeEnabled();
    });

    it('ja no ofereix «Descansa»', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/partits');

        const options = within(await screen.findByLabelText('Condició · Primer equip')).getAllByRole('option').map((o) => o.textContent);
        expect(options).toEqual(['Sense partit', 'Partit a casa', 'Partit a fora']);
    });

    it('ressalta en blau subtil els partits de casa', async () => {
        const home = { ...existingMatch, id: 51, team_id: 2, team: { id: 2, name: 'Primer equip', color: null, is_active: true, category: { id: 2, name: 'Primer equip' } }, home_away: 'home', opponent: 'CF Olot' };
        mockFetch(
            weekendServer(adminMe, (url) => (url === '/api/matchdays/7/matches' ? { status: 200, body: { data: [existingMatch, home] } } : { status: 404 })),
        );

        renderApp('/partits');

        const homeRow = (await screen.findByText('CF Olot')).closest('tr') as HTMLElement;
        const awayRow = screen.getByText('UE Figueres').closest('tr') as HTMLElement;
        expect(homeRow.className).toContain('bg-primary/[0.06]');
        expect(awayRow.className).not.toContain('bg-primary/[0.06]');

        // Un equip pendent amb «a casa» seleccionat (suggeriment) també es ressalta.
        const draftRow = screen.getByLabelText('Condició · Aleví A').closest('tr') as HTMLElement;
        expect(draftRow.className).toContain('bg-primary/[0.06]');
    });
    it('mostra els partits ja introduïts amb el seu estat', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/partits');

        const row = (await screen.findByText('UE Figueres')).closest('tr');
        expect(row).not.toBeNull();
        expect(within(row as HTMLElement).getByText('Fora')).toBeInTheDocument();
        expect(within(row as HTMLElement).getByText('Confirmat')).toBeInTheDocument();
        expect(within(row as HTMLElement).getByText('Figueres')).toBeInTheDocument();
    });
});

describe('panell del partit', () => {
    it('mostra les dades de la instal·lació per als partits a fora', async () => {
        mockFetch(weekendServer(adminMe));
        renderApp('/partits');

        await userEvent.setup().click(await screen.findByRole('button', { name: 'Edita el partit: Cadet A' }));

        expect(await screen.findByLabelText('Instal·lació')).toHaveValue('Vilatenim');
        expect(screen.getByLabelText('Municipi')).toHaveValue('Figueres');
        expect(screen.queryByLabelText('Camp')).not.toBeInTheDocument();
    });

    it('només ofereix hores en quarts entre les 09:00 i les 21:00 i limita el futbol 11 al camp complet', async () => {
        const { calls } = mockFetch(
            weekendServer(adminMe, (url, init) =>
                url === '/api/matchdays/7/matches' && init.method === 'POST' ? { status: 201, body: { data: {} } } : { status: 404 },
            ),
        );
        renderApp('/partits');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Afegeix un partit: Cadet A' }));
        await user.click(await screen.findByText('Casa'));

        const field = screen.getByLabelText('Camp');
        expect(within(field).queryByRole('option', { name: 'Camp 1' })).not.toBeInTheDocument();
        expect(within(field).getByRole('option', { name: 'Camp complet' })).toBeInTheDocument();

        const kickoff = screen.getByLabelText('Hora del partit');
        const options = within(kickoff).getAllByRole('option').map((o) => o.textContent);
        expect(options).toContain('09:00');
        expect(options).toContain('21:00');
        expect(options).not.toContain('08:45');
        expect(options).not.toContain('21:15');
        expect(options).not.toContain('10:10');

        await user.selectOptions(screen.getByLabelText('Data'), '2026-10-17');
        await user.selectOptions(kickoff, '10:15');
        await user.selectOptions(field, '3');
        // En triar l'hora, la convocatòria es proposa (09:15): es canvia per una de posterior al partit.
        expect(screen.getByLabelText('Hora de convocatòria')).toHaveValue('09:15');
        await user.clear(screen.getByLabelText('Hora de convocatòria'));
        await user.type(screen.getByLabelText('Hora de convocatòria'), '10:30');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        // La convocatòria no pot ser posterior al partit: no s'envia res.
        expect(await screen.findByText('La convocatòria no pot ser posterior a l’hora del partit.')).toBeInTheDocument();
        expect(calls.some((c) => c.url === '/api/matchdays/7/matches' && c.init.method === 'POST')).toBe(false);

        await user.clear(screen.getByLabelText('Hora de convocatòria'));
        await user.type(screen.getByLabelText('Hora de convocatòria'), '09:30');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/matchdays/7/matches' && c.init.method === 'POST')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.url === '/api/matchdays/7/matches' && c.init.method === 'POST')?.init.body)) as Record<string, unknown>;
        expect(body).toMatchObject({ team_id: 3, home_away: 'home', match_date: '2026-10-17', kickoff_time: '10:15', call_time: '09:30', field_area_id: 3 });
    });
});
