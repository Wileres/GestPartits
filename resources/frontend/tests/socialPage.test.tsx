import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { existingMatch, weekendServer } from './fixtures';
import { adminMe, mockFetch, renderApp } from './helpers';

const toastError = vi.hoisted(() => vi.fn());
vi.mock('sonner', async (importOriginal) => ({ ...(await importOriginal<typeof import('sonner')>()), toast: { error: toastError, success: vi.fn() } }));

/** jsdom no té llenç ni descodifica imatges: es substitueixen per versions mínimes. */
function stubBrowser() {
    const drawn: string[] = [];
    const ctx = new Proxy({}, { get: (_t, name: string) => (name === 'measureText' ? () => ({ width: 100 }) : name === 'createLinearGradient' ? () => ({ addColorStop: () => undefined }) : () => void drawn.push(name)), set: () => true });
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(ctx as never);
    vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation((callback) => callback(new Blob(['png'], { type: 'image/png' })));
    vi.stubGlobal('Image', class {
        width = 1600;
        height = 1200;
        onload: (() => void) | null = null;
        onerror: (() => void) | null = null;
        set src(_value: string) {
            queueMicrotask(() => this.onload?.());
        }
    });
    URL.createObjectURL = vi.fn(() => 'blob:prova');
    URL.revokeObjectURL = vi.fn();

    return drawn;
}

let drawn: string[] = [];
beforeEach(() => {
    drawn = stubBrowser();
    toastError.mockClear();
});
afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const photo = (type = 'image/jpeg', size = 1000) => new File([new Uint8Array(size)], 'equip.jpg', { type });

const withResults = (url: string) =>
    url === '/api/matchdays/7/matches'
        ? { status: 200, body: { data: [{ ...existingMatch, score_team: 7, score_opponent: 0, has_result: true }] } }
        : { status: 404 };

describe('editor d’imatges per a xarxes socials', () => {
    it('demana una foto abans de poder baixar res', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/xarxes-socials');

        expect(await screen.findByRole('heading', { level: 1, name: 'Imatge per a xarxes socials' })).toBeInTheDocument();
        expect(screen.getByText('Puja una foto per començar.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Baixa la imatge' })).toBeDisabled();
        expect(screen.getByLabelText('Ampliació')).toBeDisabled();
    });

    it('amb una foto dibuixa la publicació 4:5 i deixa baixar-la', async () => {
        mockFetch(weekendServer(adminMe));
        renderApp('/xarxes-socials');
        const user = userEvent.setup();

        await user.upload(await screen.findByLabelText('Tria una foto'), photo());

        const canvas = await screen.findByLabelText(/Publicació per a xarxes socials/);
        expect(canvas).toHaveAttribute('width', '1080');
        expect(canvas).toHaveAttribute('height', '1350');
        await waitFor(() => expect(drawn).toContain('drawImage'));
        expect(screen.getByRole('button', { name: 'Baixa la imatge' })).toBeEnabled();
        expect(screen.getByText('equip.jpg')).toBeInTheDocument();

        // Format quadrat 1:1.
        await user.click(screen.getByRole('radio', { name: /Quadrat 1:1/ }));
        await waitFor(() => expect(screen.getByLabelText(/Publicació per a xarxes socials/)).toHaveAttribute('height', '1080'));
    });

    it('rebutja fitxers que no són imatges', async () => {
        mockFetch(weekendServer(adminMe));
        renderApp('/xarxes-socials');

        // `fireEvent` perquè userEvent.upload respecta l'atribut accept i no els enviaria.
        fireEvent.change(await screen.findByLabelText('Tria una foto'), { target: { files: [photo('application/pdf')] } });

        await waitFor(() => expect(toastError).toHaveBeenCalledWith('Tria una imatge JPG, PNG o WebP de menys de 25 MB.'));
        expect(screen.getByText('Puja una foto per començar.')).toBeInTheDocument();
    });

    it('rebutja fotos de més de 25 MB', async () => {
        mockFetch(weekendServer(adminMe));
        renderApp('/xarxes-socials');

        fireEvent.change(await screen.findByLabelText('Tria una foto'), { target: { files: [photo('image/jpeg', 26 * 1024 * 1024)] } });

        await waitFor(() => expect(toastError).toHaveBeenCalled());
    });

    it('carrega l’equip, el rival i el resultat d’un partit de la jornada', async () => {
        mockFetch(weekendServer(adminMe, (url) => withResults(url)));
        renderApp('/xarxes-socials');
        const user = userEvent.setup();

        const select = await screen.findByLabelText('Carrega des d’un partit');
        await waitFor(() => expect(within(select).getByRole('option', { name: /Cadet A: 7 - 0 UE Figueres/ })).toBeInTheDocument());
        await user.selectOptions(select, within(select).getByRole('option', { name: /Cadet A: 7 - 0/ }));

        expect(screen.getByLabelText('Equip')).toHaveValue('Cadet A');
        expect(screen.getByLabelText('Gols de l’equip')).toHaveValue('7');
        expect(screen.getByLabelText('Gols del rival')).toHaveValue('0');
        expect(screen.getByLabelText('Rival')).toHaveValue('UE Figueres');
    });

    it('només accepta xifres als gols', async () => {
        mockFetch(weekendServer(adminMe));
        renderApp('/xarxes-socials');
        const user = userEvent.setup();

        const goals = await screen.findByLabelText('Gols de l’equip');
        await user.type(goals, '1a2');

        expect(goals).toHaveValue('12');
    });

    it('baixa la imatge com a PNG amb el nom de l’equip', async () => {
        mockFetch(weekendServer(adminMe));
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
        renderApp('/xarxes-socials');
        const user = userEvent.setup();

        await user.type(await screen.findByLabelText('Equip'), 'Benjamí S10 B');
        await user.upload(screen.getByLabelText('Tria una foto'), photo());
        await user.click(await screen.findByRole('button', { name: 'Baixa la imatge' }));

        await waitFor(() => expect(click).toHaveBeenCalled());
        expect(click.mock.contexts[0]).toHaveProperty('download', 'resultat-benjami-s10-b.png');
    });
});
