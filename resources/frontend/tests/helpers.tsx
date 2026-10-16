import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { vi } from 'vitest';
import { resetCsrfState } from '@/api/client';
import { routes } from '@/app/router';

export type FetchHandler = (url: string, init: RequestInit) => { status: number; body?: unknown };

/**
 * Substitueix fetch per un servidor fals i registra totes les crides.
 */
export function mockFetch(handler: FetchHandler) {
    const calls: { url: string; init: RequestInit }[] = [];

    const fetchMock = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
        const url = typeof input === 'string' ? input : input.toString();
        calls.push({ url, init });

        if (url === '/sanctum/csrf-cookie') {
            document.cookie = 'XSRF-TOKEN=token%3Dprova; path=/';

            return new Response(null, { status: 204 });
        }

        const { status, body } = handler(url, init);

        return new Response(body === undefined ? null : JSON.stringify(body), {
            status,
            headers: { 'Content-Type': 'application/json' },
        });
    });

    vi.stubGlobal('fetch', fetchMock);

    return { calls, fetchMock };
}

export function renderApp(path: string) {
    resetCsrfState();
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const router = createMemoryRouter(routes, { initialEntries: [path] });

    render(
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return { router, queryClient };
}

export const adminMe = {
    id: 1,
    username: 'Gestor',
    name: 'Gestor',
    email: null,
    role: 'admin',
    role_label: 'Administrador',
    must_change_password: false,
    permissions: { manage_users: true, manage_settings: true, view_audit: true },
};

export const managerMe = {
    ...adminMe,
    id: 2,
    username: 'gestora',
    name: 'Gestora',
    role: 'manager',
    role_label: 'Gestor',
    permissions: { manage_users: false, manage_settings: false, view_audit: false },
};
