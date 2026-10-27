import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { adminMe, mockFetch, renderApp, type FetchHandler } from './helpers';
import { reference } from './fixtures';

afterEach(() => vi.unstubAllGlobals());

const mail = { host: 'smtp.club.example', port: 587, encryption: 'tls', username: 'correu', from_address: 'avisos@club.example', from_name: 'JSPM', has_password: true, configured: true };

function server(extra: FetchHandler): FetchHandler {
    return (url, init) => {
        const custom = extra(url, init);
        if (custom.status !== 404) return custom;
        const routes: Record<string, unknown> = { '/api/auth/me': { data: adminMe }, '/api/reference-data': { data: reference }, '/api/mail-settings': { data: mail } };

        return url in routes ? { status: 200, body: routes[url] } : { status: 404 };
    };
}

describe('configuració del correu', () => {
    it('mostra la configuració sense la contrasenya i la desa', async () => {
        const { calls } = mockFetch(server((url, init) => (url === '/api/mail-settings' && init.method === 'PUT' ? { status: 200, body: { data: mail } } : { status: 404 })));
        renderApp('/configuracio/correu');
        const user = userEvent.setup();

        expect(await screen.findByLabelText('Servidor')).toHaveValue('smtp.club.example');
        expect(screen.getByLabelText('Contrasenya')).toHaveValue('');
        await user.type(screen.getByLabelText('Contrasenya'), 'nova-clau');
        await user.click(screen.getByRole('button', { name: 'Desa' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/mail-settings' && c.init.method === 'PUT')).toBe(true));
        const body = JSON.parse(String(calls.find((c) => c.init.method === 'PUT')?.init.body)) as Record<string, unknown>;
        expect(body).toMatchObject({ host: 'smtp.club.example', port: 587, password: 'nova-clau', from_address: 'avisos@club.example' });
    });

    it('envia un correu de prova', async () => {
        const { calls } = mockFetch(server((url, init) => (url === '/api/mail-settings/test' && init.method === 'POST' ? { status: 200, body: { data: { sent_to: 'admin@example.org' } } } : { status: 404 })));
        renderApp('/configuracio/correu');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Envia el correu de prova' }));

        await waitFor(() => expect(calls.some((c) => c.url === '/api/mail-settings/test')).toBe(true));
    });
});
