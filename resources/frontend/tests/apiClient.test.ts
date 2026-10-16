import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError, resetCsrfState } from '@/api/client';
import { mockFetch } from './helpers';

describe('client de l’API', () => {
    beforeEach(() => {
        resetCsrfState();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    });

    afterEach(() => vi.unstubAllGlobals());

    it('demana la cookie CSRF abans d’una escriptura i reenvia el token', async () => {
        const { calls } = mockFetch(() => ({ status: 200, body: { data: { ok: true } } }));

        await api.post('/auth/login', { username: 'gestor' });

        expect(calls[0]?.url).toBe('/sanctum/csrf-cookie');
        expect(calls[1]?.url).toBe('/api/auth/login');
        const headers = calls[1]?.init.headers as Record<string, string>;
        expect(headers['X-XSRF-TOKEN']).toBe('token=prova');
        expect(headers['Accept']).toBe('application/json');
        expect(calls[1]?.init.credentials).toBe('same-origin');
    });

    it('converteix els errors de validació en ApiError', async () => {
        mockFetch(() => ({ status: 422, body: { message: 'Error', errors: { name: ['El camp nom és obligatori.'] } } }));

        const error = await api.post('/users', {}).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).status).toBe(422);
        expect((error as ApiError).firstError('name')).toBe('El camp nom és obligatori.');
    });

    it('renova el token CSRF i reintenta un sol cop si ha caducat (419)', async () => {
        let attempts = 0;
        const { calls } = mockFetch(() => {
            attempts += 1;

            return attempts === 1 ? { status: 419, body: { message: 'La sessió ha caducat.' } } : { status: 204 };
        });

        await api.post('/auth/logout');

        expect(attempts).toBe(2);
        expect(calls.filter((c) => c.url === '/sanctum/csrf-cookie')).toHaveLength(2);
    });

    it('indica els errors de xarxa amb l’estat 0', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new TypeError('Failed to fetch'))));

        const error = await api.get('/auth/me').catch((e: unknown) => e);

        expect((error as ApiError).status).toBe(0);
    });
});
