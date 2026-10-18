import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reference, team, weekendServer } from './fixtures';
import { adminMe, managerMe, mockFetch, renderApp, type FetchHandler } from './helpers';

afterEach(() => vi.unstubAllGlobals());

const own = { weekday: 7, time: '12:30', field_area_id: 1 };

function withTeams(list: unknown[], me: unknown = adminMe, extra: FetchHandler = () => ({ status: 404 })): FetchHandler {
    return weekendServer(me, (url, init) => {
        const custom = extra(url, init);
        if (custom.status !== 404) return custom;

        return url === '/api/teams' && (init.method ?? 'GET') === 'GET' ? { status: 200, body: { data: list } } : { status: 404 };
    });
}

describe('horari preferent des de la fitxa de l’equip', () => {
    it('el gestor (no només l’administrador) l’edita des de la fitxa i s’envia amb l’equip', async () => {
        const benjami = { ...team(1, 'Benjamí A', 'f7', 'Benjamí'), preferred_schedule: own };
        const { calls } = mockFetch(withTeams([benjami], managerMe, (url, init) => (url === '/api/teams/1' && init.method === 'PUT' ? { status: 200, body: { data: benjami } } : { status: 404 })));
        renderApp('/equips');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Edita: Benjamí A' }));
        const dialog = await screen.findByRole('dialog');

        expect(within(dialog).getByLabelText('Dia preferent')).toHaveValue('7');
        expect(within(dialog).getByLabelText('Hora preferent')).toHaveValue('12:30');
        expect(within(dialog).getByLabelText('Camp preferent')).toHaveValue('1');

        await user.selectOptions(within(dialog).getByLabelText('Dia preferent'), '6');
        await user.selectOptions(within(dialog).getByLabelText('Hora preferent'), '10:15');
        await user.selectOptions(within(dialog).getByLabelText('Camp preferent'), '');
        await user.click(within(dialog).getByRole('button', { name: 'Desa' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/teams/1' && c.init.method === 'PUT')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.url === '/api/teams/1' && c.init.method === 'PUT')?.init.body)) as { preferred_schedule: unknown };
        expect(body.preferred_schedule).toEqual({ weekday: 6, time: '10:15', field_area_id: null });
    });

    it('només ofereix hores en quarts i el camp complet als equips de futbol 11', async () => {
        mockFetch(withTeams([{ ...team(2, 'Primer equip', 'f11', 'Primer equip'), preferred_schedule: null }]));
        renderApp('/equips');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Edita: Primer equip' }));
        const dialog = await screen.findByRole('dialog');
        await user.selectOptions(within(dialog).getByLabelText('Dia preferent'), '6');

        const times = within(within(dialog).getByLabelText('Hora preferent')).getAllByRole('option').map((o) => o.textContent);
        expect(times).toContain('17:00');
        expect(times).not.toContain('17:10');
        expect(times).not.toContain('08:45');
        const fields = within(within(dialog).getByLabelText('Camp preferent')).getAllByRole('option').map((o) => o.textContent);
        expect(fields).toEqual(['Qualsevol', 'Camp complet']);
    });

    it('esborra l’horari preferent enviant null i exigeix l’hora si hi ha dia', async () => {
        const benjami = { ...team(1, 'Benjamí A', 'f7', 'Benjamí'), preferred_schedule: own };
        const { calls } = mockFetch(withTeams([benjami], adminMe, (url, init) => (url === '/api/teams/1' && init.method === 'PUT' ? { status: 200, body: { data: benjami } } : { status: 404 })));
        renderApp('/equips');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Edita: Benjamí A' }));
        const dialog = await screen.findByRole('dialog');
        await user.selectOptions(within(dialog).getByLabelText('Hora preferent'), '');
        await user.click(within(dialog).getByRole('button', { name: 'Desa' }));
        expect(await within(dialog).findByText('Aquest camp és obligatori.')).toBeInTheDocument();
        expect(calls.some((c) => c.init.method === 'PUT')).toBe(false);

        await user.selectOptions(within(dialog).getByLabelText('Dia preferent'), '');
        await user.click(within(dialog).getByRole('button', { name: 'Desa' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/teams/1' && c.init.method === 'PUT')).toBe(true));
        expect((JSON.parse(String(calls.find((c) => c.init.method === 'PUT')?.init.body)) as { preferred_schedule: unknown }).preferred_schedule).toBeNull();
    });

    it('mostra l’horari heretat de la categoria si l’equip no en té de propi', async () => {
        const alevi = { ...team(1, 'Aleví A', 'f7', 'Aleví'), inherited_preference: { weekday: 6, time: '11:15', field_area_id: null, source: 'category' } };
        mockFetch(withTeams([alevi]));
        renderApp('/equips');

        expect(await screen.findByText(/dissabte 11:15 \(categoria\)/)).toBeInTheDocument();
        await userEvent.setup().click(screen.getByRole('button', { name: 'Edita: Aleví A' }));
        expect(await screen.findByText(/Ara hereta de la categoria: dissabte 11:15/)).toBeInTheDocument();
    });

    it('el panell d’un partit nou de casa avisa de l’horari que es carregarà i proposa la convocatòria', async () => {
        // Cadet A ja té un partit a la jornada: des de la seva fila es pot afegir un altre partit.
        const cadet = { ...team(3, 'Cadet A', 'f11', 'Cadet'), preferred_schedule: { weekday: 6, time: '17:00', field_area_id: 3 } };
        mockFetch(withTeams([cadet]));
        renderApp('/partits');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Afegeix un partit: Cadet A' }));
        await user.click(await screen.findByText('Casa'));

        expect(await screen.findByText('Si deixes l’horari en blanc, es carregarà l’horari preferent de l’equip: dissabte 17:00 · Camp complet.')).toBeInTheDocument();

        // En triar l'hora, la convocatòria es proposa una hora abans (i es pot canviar).
        await user.selectOptions(screen.getByLabelText('Hora del partit'), '11:15');
        expect(screen.getByLabelText('Hora de convocatòria')).toHaveValue('10:15');
        expect(screen.getByText('Per defecte, una hora abans del partit.')).toBeInTheDocument();

        await user.clear(screen.getByLabelText('Hora de convocatòria'));
        await user.type(screen.getByLabelText('Hora de convocatòria'), '10:00');
        await user.selectOptions(screen.getByLabelText('Hora del partit'), '11:30');
        expect(screen.getByLabelText('Hora de convocatòria')).toHaveValue('10:00');
        expect(reference.field_areas).toHaveLength(3);
    });

    it('només hi ha els estats Proposat, Confirmat i Cancel·lat, i un partit nou és Confirmat', async () => {
        mockFetch(withTeams([team(3, 'Cadet A', 'f11', 'Cadet')]));
        renderApp('/partits');

        await userEvent.setup().click(await screen.findByRole('button', { name: 'Afegeix un partit: Cadet A' }));
        const status = await screen.findByLabelText('Estat');

        expect(within(status).getAllByRole('option').map((o) => o.textContent)).toEqual(['Proposat', 'Confirmat', 'Cancel·lat']);
        expect(status).toHaveValue('confirmed');
    });
});
