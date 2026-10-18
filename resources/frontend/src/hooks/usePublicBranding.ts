import { useQuery } from '@tanstack/react-query';
import { publicBrandingApi } from '@/api/communications';

export const publicBrandingKey = ['public-branding'] as const;

/**
 * Escut i nom del club (compartits per tots els equips). Funciona sense sessió.
 */
export function usePublicBranding() {
    return useQuery({ queryKey: publicBrandingKey, queryFn: publicBrandingApi.get, staleTime: 10 * 60 * 1000, retry: 1 });
}
