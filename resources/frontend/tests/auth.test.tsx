import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { adminMe, managerMe, mockFetch, renderApp } from './helpers';

afterEach(() => vi.unstubAllGlobals());

describe('inici de sessió', () => {
    it('mostra el formulari en català i l’error del servidor', async () => {
        mockFetch((url) => {
            if (url === '/api/auth/me') return { status: 401, body: { message: 'Cal iniciar la sessió.' } };
            if (url === '/api/auth/login') {
                return { status: 422, body: { message: 'Error', errors: { username: ['L\'usuari o la contrasenya no són correctes.'] } } };
            }

            return { status: 404 };
        });

        renderApp('/inicia-sessio');

        const user = userEvent.setup();
        await user.type(await screen.findByLabelText('Usuari'), 'Gestor');
        await user.type(screen.getByLabelText('Contrasenya'), 'incorrecta');
        await user.click(screen.getByRole('button', { name: 'Inicia la sessió' }));

        expect(await screen.findByText('L\'usuari o la contrasenya no són correctes.')).toBeInTheDocument();
    });

    it('valida els camps obligatoris abans d’enviar', async () => {
        const { calls } = mockFetch(() => ({ status: 401, body: {} }));

        renderApp('/inicia-sessio');

        await userEvent.setup().click(await screen.findByRole('button', { name: 'Inicia la sessió' }));

        expect(await screen.findAllByText('Aquest camp és obligatori.')).toHaveLength(2);
        expect(calls.some((c) => c.url === '/api/auth/login')).toBe(false);
    });

    it('envia l’usuari amb contrasenya temporal a canviar-la', async () => {
        mockFetch((url) => {
            if (url === '/api/auth/me') return { status: 401, body: {} };
            if (url === '/api/auth/login') return { status: 200, body: { data: { ...adminMe, must_change_password: true } } };

            return { status: 404 };
        });

        const { router } = renderApp('/inicia-sessio');

        const user = userEvent.setup();
        await user.type(await screen.findByLabelText('Usuari'), 'Gestor');
        await user.type(screen.getByLabelText('Contrasenya'), 'ContrasenyaInicial');
        await user.click(screen.getByRole('button', { name: 'Inicia la sessió' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/canvia-contrasenya'));
        expect(await screen.findByRole('heading', { name: 'Canvia la contrasenya' })).toBeInTheDocument();
    });
});

describe('rutes protegides', () => {
    it('redirigeix a l’inici de sessió sense sessió', async () => {
        mockFetch(() => ({ status: 401, body: {} }));

        const { router } = renderApp('/usuaris');

        await waitFor(() => expect(router.state.location.pathname).toBe('/inicia-sessio'));
    });

    it('no deixa sortir de la pantalla de canvi de contrasenya', async () => {
        mockFetch(() => ({ status: 200, body: { data: { ...adminMe, must_change_password: true } } }));

        const { router } = renderApp('/usuaris');

        await waitFor(() => expect(router.state.location.pathname).toBe('/canvia-contrasenya'));
    });

    it('mostra la programació a l’usuari autenticat', async () => {
        mockFetch(() => ({ status: 200, body: { data: adminMe } }));

        renderApp('/');

        expect(await screen.findByRole('heading', { name: 'Programació de la jornada' })).toBeInTheDocument();
    });

    it('amaga la gestió d’usuaris al gestor', async () => {
        mockFetch(() => ({ status: 200, body: { data: managerMe } }));

        renderApp('/usuaris');

        expect(await screen.findByText('No tens permís per veure aquesta pàgina.')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Usuaris' })).not.toBeInTheDocument();
    });
});
