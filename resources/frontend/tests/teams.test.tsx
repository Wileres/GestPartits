import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { weekendServer } from './fixtures';
import { adminMe, managerMe, mockFetch, renderApp } from './helpers';

afterEach(() => vi.unstubAllGlobals());

describe('equips', () => {
    it('es llisten en ordre amb els valors efectius i un control d’arrossegament en català', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/equips');

        const items = await screen.findAllByRole('listitem');
        expect(items[0]).toHaveTextContent('Aleví A');
        expect(items[1]).toHaveTextContent('Primer equip');
        expect(items[1]).toHaveTextContent('Futbol 11');
        expect(screen.getByRole('button', { name: 'Arrossega per reordenar Aleví A' })).toBeInTheDocument();
    });
});

describe('configuració', () => {
    it('el gestor no hi té accés', async () => {
        mockFetch(weekendServer(managerMe));

        renderApp('/configuracio/regles');

        expect(await screen.findByText('No tens permís per veure aquesta pàgina.')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Regles horàries' })).not.toBeInTheDocument();
    });

    it('l’administrador veu el menú de configuració', async () => {
        mockFetch(weekendServer(adminMe));

        renderApp('/equips');

        expect(await screen.findByRole('link', { name: 'Regles horàries' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Franges horàries' })).toBeInTheDocument();
    });
});
