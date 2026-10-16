/**
 * Client HTTP de l'API. Autenticació per cookie de sessió (Sanctum SPA) amb protecció CSRF:
 * la cookie XSRF-TOKEN es reenvia a la capçalera X-XSRF-TOKEN.
 */

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: FieldErrors = {},
        public readonly code: string | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }

    firstError(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

let csrfReady: Promise<void> | null = null;

function readCookie(name: string): string | null {
    const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));

    return match === undefined ? null : decodeURIComponent(match.slice(name.length + 1));
}

export function ensureCsrfCookie(force = false): Promise<void> {
    if (force || csrfReady === null || readCookie('XSRF-TOKEN') === null) {
        csrfReady = fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(() => undefined);
    }

    return csrfReady;
}

/** Només per a les proves. */
export function resetCsrfState(): void {
    csrfReady = null;
}

async function send(method: Method, url: string, body?: unknown): Promise<Response> {
    if (method !== 'GET') {
        await ensureCsrfCookie();
    }

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = readCookie('XSRF-TOKEN');
    if (token !== null) {
        headers['X-XSRF-TOKEN'] = token;
    }

    const isForm = body instanceof FormData;
    if (body !== undefined && !isForm) {
        headers['Content-Type'] = 'application/json';
    }

    return fetch(`/api${url}`, {
        method,
        headers,
        credentials: 'same-origin',
        // Amb FormData el navegador posa el Content-Type multipart amb el boundary.
        body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
    });
}

export async function request<T>(method: Method, url: string, body?: unknown): Promise<T> {
    let response: Response;

    try {
        response = await send(method, url, body);

        // Token CSRF caducat: se'n demana un de nou i es reintenta un sol cop.
        if (response.status === 419) {
            await ensureCsrfCookie(true);
            response = await send(method, url, body);
        }
    } catch {
        throw new ApiError(0, 'network');
    }

    if (response.status === 204) {
        return undefined as T;
    }

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const data = (payload ?? {}) as { message?: string; errors?: FieldErrors; code?: string };

        throw new ApiError(response.status, data.message ?? '', data.errors ?? {}, data.code ?? null);
    }

    return payload as T;
}

export const api = {
    request,
    get: <T>(url: string) => request<T>('GET', url),
    post: <T>(url: string, body?: unknown) => request<T>('POST', url, body),
    put: <T>(url: string, body?: unknown) => request<T>('PUT', url, body),
    patch: <T>(url: string, body?: unknown) => request<T>('PATCH', url, body),
    delete: <T>(url: string) => request<T>('DELETE', url),
};
