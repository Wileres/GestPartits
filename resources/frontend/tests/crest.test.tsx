import { screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { weekendServer } from './fixtures';
import { adminMe, mockFetch, renderApp } from './helpers';

afterEach(() => vi.unstubAllGlobals());

describe('escut del club', () => {
    it('surt a la pantalla d’accés, abans d’iniciar la sessió', async () => {
        mockFetch((url) => {
            if (url === '/api/auth/me') return { status: 401, body: {} };
            if (url === '/api/public/branding') return { status: 200, body: { data: { club_name: 'Joventut Sant Pere Màrtir', logo_url: '/api/public/logo?v=abc' } } };

            return { status: 404 };
        });

        renderApp('/inicia-sessio');

        const crest = await screen.findByAltText('Joventut Sant Pere Màrtir');
        expect(crest).toHaveAttribute('src', '/api/public/logo?v=abc');
    });

    it('mentre no se n’ha pujat cap, es veu un escut genèric', async () => {
        mockFetch((url) => {
            if (url === '/api/auth/me') return { status: 401, body: {} };
            if (url === '/api/public/branding') return { status: 200, body: { data: { club_name: 'Joventut Sant Pere Màrtir', logo_url: null } } };

            return { status: 404 };
        });

        renderApp('/inicia-sessio');

        expect(await screen.findByRole('img', { name: 'Joventut Sant Pere Màrtir' })).toBeInTheDocument();
    });

    it('és el mateix escut per a tots els equips: la llista d’equips el mostra a cada fila i la fitxa no en té un de propi', async () => {
        mockFetch(
            weekendServer(adminMe, (url) => (url === '/api/public/branding' ? { status: 200, body: { data: { club_name: 'Joventut Sant Pere Màrtir', logo_url: '/api/public/logo?v=abc' } } } : { status: 404 })),
        );

        renderApp('/equips');

        // Menú d'ordinador i del mòbil (2) + una fila per cada equip (3).
        await waitFor(() => expect(screen.getAllByAltText('Joventut Sant Pere Màrtir')).toHaveLength(5));
        expect(screen.queryByText('Puja l’escut')).not.toBeInTheDocument();
    });
});
