import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { matchday, reference, season, teams } from './fixtures';
import { adminMe, mockFetch, renderApp, type FetchHandler } from './helpers';

afterEach(() => vi.unstubAllGlobals());

const confirmedSchedule = { id: 3, status: 'confirmed', version: 9, matches: [], conflicts: [], blocking_conflicts: [], versions: [], input: { dates: [], step_min: 15, operating: { first: '09:00', last: '21:00' }, slots: [], blocks: [], rules: [], matches: [] } };

const base = {
    match_id: 1, opponent: 'CF Olot', is_summary: false, provider: 'manual', provider_label: 'Manual (copiar i enganxar)', is_manual: true,
    status: 'draft', status_label: 'Esborrany', attempts: 0, error_message: null, queued_at: null, sent_at: null, attachments: [],
    can_edit: true, can_send: false, can_mark_sent: true, can_discard: true,
};
const manual = { ...base, id: 11, team: 'Aleví A', destination: { id: 1, label: 'Famílies Aleví A', type: 'group', type_label: 'Grup', team: 'Aleví A' }, body: '🏠 Aleví A - CF Olot', share_url: 'https://api.whatsapp.com/send?text=%F0%9F%8F%A0' };
const automatic = { ...base, id: 12, team: 'Cadet A', destination: { id: 2, label: 'Delegat Cadet', type: 'phone', type_label: 'Telèfon', team: 'Cadet A' }, provider: 'log', provider_label: 'Simulació', is_manual: false, body: '🚌 UE Figueres - Cadet A', share_url: null, can_send: true, can_mark_sent: false };

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
            '/api/matchdays/7/schedule': { data: confirmedSchedule },
            '/api/schedules/3/whatsapp-messages': { data: [] },
        };

        return url in routes && (init.method ?? 'GET') === 'GET' ? { status: 200, body: routes[url] } : { status: 404 };
    };
}

describe('avisos de WhatsApp', () => {
    it('prepara els avisos sense enviar-ne cap i ofereix les accions manuals', async () => {
        const { calls } = mockFetch(server((url, init) => (url === '/api/schedules/3/whatsapp/preview' && init.method === 'POST' ? { status: 200, body: { data: [manual, automatic] } } : { status: 404 })));
        renderApp('/avisos');
        const user = userEvent.setup();

        await user.click(await screen.findByRole('button', { name: 'Prepara els avisos' }));

        const card = (await screen.findByText('Famílies Aleví A', { exact: false })).closest('[data-slot=card]') as HTMLElement;
        expect(within(card).getByRole('button', { name: 'Copia el text' })).toBeInTheDocument();
        expect(within(card).getByRole('link', { name: 'Obre WhatsApp' })).toHaveAttribute('href', manual.share_url);
        expect(within(card).getByRole('button', { name: 'Marca com a enviat' })).toBeInTheDocument();
        expect(calls.some((c) => c.url.endsWith('/whatsapp/send'))).toBe(false);
    });

    it('l’enviament automàtic exigeix seleccionar i confirmar', async () => {
        const { calls } = mockFetch(
            server((url, init) => {
                if (url === '/api/schedules/3/whatsapp-messages') return { status: 200, body: { data: [manual, automatic] } };
                if (url === '/api/schedules/3/whatsapp/send' && init.method === 'POST') {
                    return { status: 200, body: { data: { queued: [12], skipped: [], messages: [manual, { ...automatic, status: 'queued', can_send: false }] } } };
                }

                return { status: 404 };
            }),
        );
        renderApp('/avisos');
        const user = userEvent.setup();

        const sendButton = await screen.findByRole('button', { name: 'Envia els avisos seleccionats (0)' });
        expect(sendButton).toBeDisabled();

        await user.click(screen.getByLabelText('Selecciona Delegat Cadet'));
        await user.click(screen.getByRole('button', { name: 'Envia els avisos seleccionats (1)' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText('Enviar 1 avisos?')).toBeInTheDocument();
        expect(calls.some((c) => c.url.endsWith('/whatsapp/send'))).toBe(false);

        await user.click(within(dialog).getByRole('button', { name: 'Envia' }));

        await waitFor(() => expect(calls.some((c) => c.url.endsWith('/whatsapp/send'))).toBe(true));
        expect(JSON.parse(String(calls.find((c) => c.url.endsWith('/whatsapp/send'))?.init.body))).toEqual({ message_ids: [12], confirm: true });
    });

    it('demana confirmar la programació abans', async () => {
        mockFetch(server((url) => (url === '/api/matchdays/7/schedule' ? { status: 200, body: { data: { ...confirmedSchedule, status: 'proposed' } } } : { status: 404 })));

        renderApp('/avisos');

        expect(await screen.findByText('Cal confirmar la programació abans de generar les imatges i enviar els avisos.')).toBeInTheDocument();
    });
});
