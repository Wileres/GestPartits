import { useQuery } from '@tanstack/react-query';
import { referenceApi } from '@/api/catalog';

export const referenceKey = ['reference-data'] as const;

export function useReferenceData() {
    return useQuery({ queryKey: referenceKey, queryFn: referenceApi.get, staleTime: 10 * 60 * 1000 });
}
