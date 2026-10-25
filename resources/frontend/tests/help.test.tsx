import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ca } from '@/i18n/ca';
import { matchday, weekendServer } from './fixtures';
import { adminMe, mockFetch, renderApp } from './helpers';

afterEach(() => vi.unstubAllGlobals());

const newer = { ...matchday, id: 8, starts_on: '2026-10-24', ends_on: '2026-10-25', display_label: 'Jornada del 24/10/2026 al 25/10/2026' };

/** Dues jornades: la 7 és la que cau més endavant al calendari, però la creada més tard és la 8. */
const farFuture = { ...matchday, starts_on: '2099-10-17', ends_on: '2099-10-18', display_label: 'Jornada del 17/10/2099 al 18/10/2099' };
const twoMatchdays = (url: string) => {
    if (url === '/api/seasons/1/matchdays') return { status: 200, body: { data: [farFuture, newer] } };
    if (url === '/api/matchdays/8/matches') return { status: 200, body: { data: [] } };
    if (url === '/api/matchdays/8/home-away-suggestions') return { status: 200, body: { data: {} } };

    return { status: 404 };
};

describe('jornada per defecte', () => {
    it.each(['/', '/partits', '/avisos'])('%s carrega l’última jornada creada', async (path) => {
        const { calls } = mockFetch(weekendServer(adminMe, (url) => twoMatchdays(url)));

        renderApp(path);

        await waitFor(() => expect(calls.some((c) => c.url.startsWith('/api/matchdays/8/'))).toBe(true));
        expect(calls.some((c) => c.url.startsWith('/api/matchdays/7/'))).toBe(false);
    });
});

describe('ajuda', () => {
    it('el menú porta a la guia ràpida i a la guia completa', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/partits');

        const nav = await screen.findByRole('navigation', { name: 'Navegació principal' });
        expect(within(nav).getByText('Ajuda')).toBeInTheDocument();
        expect(within(nav).getByRole('link', { name: 'Guia ràpida' })).toHaveAttribute('href', '/ajuda/guia-rapida');
        expect(within(nav).getByRole('link', { name: 'Guia completa' })).toHaveAttribute('href', '/ajuda/guia-completa');
    });

    it('la guia ràpida mostra tots els passos numerats i enllaça amb la completa', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/ajuda/guia-rapida');

        expect(await screen.findByRole('heading', { level: 1, name: 'Guia ràpida' })).toBeInTheDocument();
        const steps = Object.values(ca.help.quick.steps);
        for (const step of steps) {
            expect(screen.getByRole('heading', { name: step.title })).toBeInTheDocument();
        }
        expect(screen.getAllByRole('listitem').length).toBeGreaterThanOrEqual(steps.length);

        const user = userEvent.setup();
        await user.click(screen.getByRole('link', { name: 'Consulta la guia completa' }));
        expect(await screen.findByRole('heading', { level: 1, name: 'Guia completa' })).toBeInTheDocument();
    });

    it('la guia completa té un índex amb una secció per cada apartat', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/ajuda/guia-completa');

        const toc = await screen.findByRole('navigation', { name: 'Contingut' });
        const sections = Object.entries(ca.help.full.sections);
        expect(within(toc).getAllByRole('link')).toHaveLength(sections.length);
        for (const [id, section] of sections) {
            expect(within(toc).getByRole('link', { name: section.title })).toHaveAttribute('href', `#guia-${id}`);
            expect(document.getElementById(`guia-${id}`)).not.toBeNull();
        }
    });
});
