import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/api/client';
import { meQueryKey } from '@/hooks/useAuth';

/**
 * Si el servidor indica que la sessió ha caducat (401) o que cal canviar la contrasenya,
 * es refresca l'usuari i els guards de les rutes redirigeixen on toca.
 */
export function createQueryClient(): QueryClient {
    const client: QueryClient = new QueryClient({
        queryCache: new QueryCache({ onError: (error) => handleAuthError(error) }),
        mutationCache: new MutationCache({ onError: (error) => handleAuthError(error) }),
        defaultOptions: {
            queries: {
                retry: (failureCount, error) => !(error instanceof ApiError && error.status < 500) && failureCount < 2,
                refetchOnWindowFocus: false,
            },
        },
    });

    function handleAuthError(error: unknown): void {
        if (!(error instanceof ApiError)) return;

        if (error.status === 401) {
            client.setQueryData(meQueryKey, null);
        } else if (error.status === 403 && error.code === 'password_change_required') {
            void client.invalidateQueries({ queryKey: meQueryKey });
        }
    }

    return client;
}
