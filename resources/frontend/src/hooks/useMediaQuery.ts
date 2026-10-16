import { useSyncExternalStore } from 'react';

/**
 * Cert si la consulta de mitjans es compleix. Sense matchMedia (proves), es considera pantalla gran.
 */
export function useMediaQuery(query: string): boolean {
    return useSyncExternalStore(
        (onChange) => {
            if (typeof window.matchMedia !== 'function') return () => undefined;
            const list = window.matchMedia(query);
            list.addEventListener('change', onChange);

            return () => list.removeEventListener('change', onChange);
        },
        () => (typeof window.matchMedia === 'function' ? window.matchMedia(query).matches : true),
    );
}
