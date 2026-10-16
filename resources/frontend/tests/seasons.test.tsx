import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { weekendServer } from './fixtures';
import { adminMe, mockFetch, renderApp } from './helpers';

afterEach(() => vi.unstubAllGlobals());

describe('jornades', () => {
    it('es mostren amb les dates en format DD/MM/AAAA', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/temporades');

        expect(await screen.findByText('Jornada del 17/10/2026 al 18/10/2026')).toBeInTheDocument();
        expect(screen.getByText(/17\/10\/2026 – 18\/10\/2026/)).toBeInTheDocument();
        expect(screen.getByText('01/09/2026 – 30/06/2027')).toBeInTheDocument();
    });

    it('es creen escrivint les dates en format DD/MM/AAAA', async () => {
        const { calls } = mockFetch(
            weekendServer(adminMe, (url, init) => (url === '/api/seasons/1/matchdays' && init.method === 'POST' ? { status: 201, body: { data: {} } } : { status: 404 })),
        );
        renderApp('/temporades');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Nova jornada' }));
        const first = screen.getByLabelText('Primer dia');
        await user.clear(first);
        await user.type(first, '24102026');

        expect(first).toHaveValue('24/10/2026');
        expect(screen.getByLabelText('Últim dia')).toHaveValue('25/10/2026');

        await user.type(screen.getByLabelText('Nom (opcional)'), 'Jornada 6');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/seasons/1/matchdays' && c.init.method === 'POST')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.url === '/api/seasons/1/matchdays' && c.init.method === 'POST')?.init.body)) as unknown;
        expect(body).toEqual({ starts_on: '2026-10-24', ends_on: '2026-10-25', label: 'Jornada 6' });
    });

    it('rebutja dates impossibles', async () => {
        const { calls } = mockFetch(weekendServer(adminMe));
        renderApp('/temporades');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Nova jornada' }));
        const last = screen.getByLabelText('Últim dia');
        await user.clear(last);
        await user.type(last, '31022027');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        expect(await screen.findByText('Escriu una data vàlida (dd/mm/aaaa).')).toBeInTheDocument();
        expect(calls.some((c) => c.init.method === 'POST' && c.url.includes('matchdays'))).toBe(false);
    });
});
