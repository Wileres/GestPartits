import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { existingMatch, matchday, reference, season, teams, weekendServer } from './fixtures';
import { adminMe, mockFetch, renderApp, type FetchHandler } from './helpers';

afterEach(() => vi.unstubAllGlobals());

const confirmedSchedule = {
    id: 3, status: 'confirmed', status_label: 'Confirmada', version: 9, confirmed_at: null, confirmed_by: null, has_unsaved_changes: false, can_confirm: false, matchday,
    matches: [existingMatch], conflicts: [], blocking_conflicts: [], versions: [], proposal: null,
    input: { dates: ['2026-10-17', '2026-10-18'], step_min: 15, operating: { first: '09:00', last: '21:00' }, slots: [], blocks: [], rules: [], matches: [] },
};

const image = (id: number, extra: Record<string, unknown> = {}) => ({
    id, kind: 'schedule', batch_id: 'B1', day: '2026-10-17', weekday: 'dissabte', page: 1, total_pages: 1, width: 1080, height: 1920, renderer: 'chromium',
    url: `/api/generated-images/${id}?v=abc`, filename: `jornada-2026-10-17-${id}.png`, warnings: [], created_at: '2026-10-26T20:00:00+00:00', ...extra,
});

function scheduleServer(extra: FetchHandler): FetchHandler {
    return (url, init) => {
        const custom = extra(url, init);
        if (custom.status !== 404) return custom;
        const routes: Record<string, unknown> = {
            '/api/auth/me': { data: adminMe },
            '/api/reference-data': { data: reference },
            '/api/seasons': { data: [season] },
            '/api/seasons/1/matchdays': { data: [matchday] },
            '/api/teams': { data: teams },
            '/api/matchdays/7/schedule': { data: confirmedSchedule },
        };

        return url in routes && (init.method ?? 'GET') === 'GET' ? { status: 200, body: routes[url] } : { status: 404 };
    };
}

describe('imatges del calendari', () => {
    it('permet separar els partits a casa i a fora i ho envia en generar', async () => {
        const { calls } = mockFetch(
            scheduleServer((url, init) => {
                if (url === '/api/schedules/3/images' && init.method === 'POST') return { status: 202, body: { data: { generating: true, confirmed: true, images: [] } } };
                if (url === '/api/schedules/3/images') return { status: 200, body: { data: { generating: false, confirmed: true, images: [] } } };

                return { status: 404 };
            }),
        );
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Genera les imatges' }));
        const dialog = await screen.findByRole('dialog');
        await user.click(within(dialog).getByRole('checkbox', { name: /Separa els partits a casa i a fora/ }));
        await user.click(within(dialog).getByRole('button', { name: 'Genera les imatges' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/schedules/3/images' && c.init.method === 'POST')).toBe(true));
        const post = calls.find((c) => c.url === '/api/schedules/3/images' && c.init.method === 'POST');
        expect(JSON.parse(String(post?.init.body))).toEqual({ group_home_away: true, format: 'story', compress: false });
    });

    it('permet triar el format de publicació d’Instagram i comprimir els partits d’un dia', async () => {
        const { calls } = mockFetch(
            scheduleServer((url, init) => {
                if (url === '/api/schedules/3/images' && init.method === 'POST') return { status: 202, body: { data: { generating: true, confirmed: true, images: [] } } };
                if (url === '/api/schedules/3/images') return { status: 200, body: { data: { generating: false, confirmed: true, images: [] } } };

                return { status: 404 };
            }),
        );
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Genera les imatges' }));
        const dialog = await screen.findByRole('dialog');
        // Per defecte, la història de 1080 × 1920, sense comprimir.
        expect(within(dialog).getByRole('radio', { name: 'Història (1080 × 1920)' })).toBeChecked();
        expect(within(dialog).getByRole('checkbox', { name: /Comprimeix tots els partits d’un dia/ })).not.toBeChecked();

        await user.click(within(dialog).getByRole('radio', { name: 'Publicació d’Instagram (1080 × 1350)' }));
        await user.click(within(dialog).getByRole('checkbox', { name: /Comprimeix tots els partits d’un dia/ }));
        await user.click(within(dialog).getByRole('button', { name: 'Genera les imatges' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/schedules/3/images' && c.init.method === 'POST')).toBe(true));
        const post = calls.find((c) => c.url === '/api/schedules/3/images' && c.init.method === 'POST');
        expect(JSON.parse(String(post?.init.body))).toEqual({ group_home_away: false, format: 'post', compress: true });
    });

    it('mostra l’última generació i en deixa esborrar una imatge amb confirmació', async () => {
        const { calls } = mockFetch(
            scheduleServer((url, init) => {
                if (url === '/api/schedules/3/images') return { status: 200, body: { data: { generating: false, confirmed: true, images: [image(1), image(2, { day: '2026-10-18', weekday: 'diumenge' })] } } };
                if (url === '/api/generated-images/1' && init.method === 'DELETE') return { status: 204 };

                return { status: 404 };
            }),
        );
        renderApp('/');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Genera les imatges' }));
        const dialog = await screen.findByRole('dialog');
        expect(await within(dialog).findByText(/Última generació/)).toBeInTheDocument();
        expect(within(dialog).getByRole('link', { name: 'Històric d’imatges' })).toHaveAttribute('href', '/imatges');

        await user.click(within(dialog).getAllByRole('button', { name: /Esborra la imatge de/ })[0] as HTMLElement);
        // Primer es demana confirmació: res no s'esborra encara.
        expect(calls.some((c) => c.init.method === 'DELETE')).toBe(false);
        const confirm = (await screen.findAllByRole('dialog')).at(-1) as HTMLElement;
        await user.click(within(confirm).getByRole('button', { name: 'Esborra' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/generated-images/1' && c.init.method === 'DELETE')).toBe(true));
    });
});

const batch = (id: string, kind: 'schedule' | 'results', images: unknown[]) => ({
    batch_id: id, kind, matchday: { id: 7, display_label: 'Jornada del 17/10/2026 al 18/10/2026' }, options: kind === 'schedule' ? { group_home_away: true } : null,
    created_at: '2026-10-26T20:00:00+00:00', created_by: 'Gestor', images,
});

describe('històric d’imatges', () => {
    const history = (url: string) => {
        if (url === '/api/generated-images') return { status: 200, body: { data: [batch('B2', 'results', [image(3, { kind: 'results', batch_id: 'B2', filename: 'resultats-2026-10-17.png' })]), batch('B1', 'schedule', [image(1), image(2)])] } };
        if (url === '/api/generated-images?kind=results') return { status: 200, body: { data: [batch('B2', 'results', [image(3, { kind: 'results', batch_id: 'B2' })])] } };

        return { status: 404 };
    };

    it('llista totes les generacions amb descàrrega i opcions', async () => {
        mockFetch(weekendServer(adminMe, history));

        renderApp('/imatges');

        expect(await screen.findByRole('heading', { level: 1, name: 'Imatges generades' })).toBeInTheDocument();
        expect(await screen.findAllByText('Jornada del 17/10/2026 al 18/10/2026')).toHaveLength(2);
        expect(screen.getByText('Separats a casa i a fora', { exact: false })).toBeInTheDocument();
        // Les generacions antigues, sense format guardat, són històries.
        expect(screen.getByText(/Història \(1080 × 1920\)/)).toBeInTheDocument();
        const downloads = screen.getAllByRole('link', { name: 'Baixa la imatge' });
        expect(downloads).toHaveLength(3);
        expect(downloads[0]).toHaveAttribute('download', 'resultats-2026-10-17.png');
    });

    it('indica el format, si estan comprimides i com s’han agrupat', async () => {
        const post = { ...batch('B3', 'schedule', [image(5, { batch_id: 'B3', width: 1080, height: 1350 })]), options: { group_home_away: false, format: 'post', compress: true } };
        mockFetch(weekendServer(adminMe, (url) => (url === '/api/generated-images' ? { status: 200, body: { data: [post] } } : { status: 404 })));

        renderApp('/imatges');

        expect(await screen.findByText(/Publicació d’Instagram \(1080 × 1350\) · Comprimides · En ordre d’hora/)).toBeInTheDocument();
        // La previsualització respecta la proporció real de la imatge (4:5).
        const preview = screen.getByRole('img');
        expect(preview).toHaveAttribute('width', '1080');
        expect(preview).toHaveAttribute('height', '1350');
    });

    it('filtra per tipus', async () => {
        const { calls } = mockFetch(weekendServer(adminMe, (url) => history(url)));
        renderApp('/imatges');
        const user = userEvent.setup();

        await screen.findAllByText('Jornada del 17/10/2026 al 18/10/2026');
        await user.click(screen.getByRole('button', { name: 'Resultats' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/generated-images?kind=results')).toBe(true));
        await waitFor(() => expect(screen.getAllByRole('link', { name: 'Baixa la imatge' })).toHaveLength(1));
    });

    it('esborra una generació sencera només després de confirmar-ho', async () => {
        const { calls } = mockFetch(
            weekendServer(adminMe, (url, init) => (url === '/api/generated-images/batches/B1' && init.method === 'DELETE' ? { status: 204 } : history(url))),
        );
        renderApp('/imatges');
        const user = userEvent.setup();

        const buttons = await screen.findAllByRole('button', { name: 'Esborra la generació' });
        await user.click(buttons[1] as HTMLElement);
        expect(calls.some((c) => c.init.method === 'DELETE')).toBe(false);
        const confirm = await screen.findByRole('dialog');
        await user.click(within(confirm).getByRole('button', { name: 'Esborra' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/generated-images/batches/B1' && c.init.method === 'DELETE')).toBe(true));
    });

    it('explica que encara no hi ha cap imatge', async () => {
        mockFetch(weekendServer(adminMe, (url) => (url === '/api/generated-images' ? { status: 200, body: { data: [] } } : { status: 404 })));

        renderApp('/imatges');

        expect(await screen.findByText('Encara no s’ha generat cap imatge.')).toBeInTheDocument();
    });
});

describe('resultats dels partits', () => {
    const withResult = { ...existingMatch, score_team: 3, score_opponent: 1, has_result: true };

    it('mostra el resultat a la llista de partits', async () => {
        mockFetch(weekendServer(adminMe, (url) => (url === '/api/matchdays/7/matches' ? { status: 200, body: { data: [withResult] } } : { status: 404 })));

        renderApp('/partits');

        const row = (await screen.findByText('UE Figueres')).closest('tr') as HTMLElement;
        expect(within(row).getByLabelText('Resultat: Cadet A 3, rival 1')).toHaveTextContent('3 - 1');
    });

    it('desa els gols des de la fitxa del partit', async () => {
        const { calls } = mockFetch(
            weekendServer(adminMe, (url, init) => (url === '/api/matches/50' && init.method === 'PUT' ? { status: 200, body: { data: withResult } } : { status: 404 })),
        );
        renderApp('/partits');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Edita el partit: Cadet A' }));
        await user.type(await screen.findByLabelText('Gols de Cadet A'), '3');
        await user.type(screen.getByLabelText('Gols del rival'), '1');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/matches/50' && c.init.method === 'PUT')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.url === '/api/matches/50' && c.init.method === 'PUT')?.init.body)) as Record<string, unknown>;
        expect(body).toMatchObject({ score_team: 3, score_opponent: 1 });
    });

    it('exigeix els gols dels dos equips', async () => {
        const { calls } = mockFetch(weekendServer(adminMe));
        renderApp('/partits');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Edita el partit: Cadet A' }));
        await user.type(await screen.findByLabelText('Gols de Cadet A'), '2');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        expect(await screen.findByText('Indica els gols dels dos equips.')).toBeInTheDocument();
        expect(calls.some((c) => c.init.method === 'PUT')).toBe(false);
    });

    it('la imatge de resultats es genera des de la pàgina de partits', async () => {
        const { calls } = mockFetch(
            weekendServer(adminMe, (url, init) => {
                if (url === '/api/matchdays/7/result-images' && init.method === 'POST') return { status: 202, body: { data: { generating: true, results_count: 2, images: [] } } };
                if (url === '/api/matchdays/7/result-images') return { status: 200, body: { data: { generating: false, results_count: 2, images: [] } } };

                return { status: 404 };
            }),
        );
        renderApp('/partits');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Imatge de resultats' }));
        const dialog = await screen.findByRole('dialog');
        expect(await within(dialog).findByText('Hi ha 2 partits amb resultat.')).toBeInTheDocument();
        await user.click(within(dialog).getByRole('button', { name: 'Genera la imatge de resultats' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/matchdays/7/result-images' && c.init.method === 'POST')).toBe(true));
    });

    it('avisa que no hi ha resultats i no deixa generar la imatge', async () => {
        mockFetch(weekendServer(adminMe, (url) => (url === '/api/matchdays/7/result-images' ? { status: 200, body: { data: { generating: false, results_count: 0, images: [] } } } : { status: 404 })));
        renderApp('/partits');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Imatge de resultats' }));
        const dialog = await screen.findByRole('dialog');

        expect(await within(dialog).findByText(/Encara no hi ha cap resultat en aquesta jornada/)).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Genera la imatge de resultats' })).toBeDisabled();
    });
});
