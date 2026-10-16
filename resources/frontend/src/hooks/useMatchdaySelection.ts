import { useSearchParams } from 'react-router';
import type { Matchday, Season } from '@/api/types';
import { useMatchdays, useSeasons } from '@/features/seasons/useSeasons';

/**
 * Temporada i jornada seleccionades (a la URL, perquè es puguin compartir i recarregar).
 * Per defecte: la temporada actual i la primera jornada que encara no ha acabat.
 */
export function useMatchdaySelection() {
    const [params, setParams] = useSearchParams();
    const seasons = useSeasons();

    const seasonParam = Number(params.get('temporada'));
    const season: Season | null =
        seasons.data?.find((s) => s.id === seasonParam) ?? seasons.data?.find((s) => s.is_current) ?? seasons.data?.[0] ?? null;

    const matchdays = useMatchdays(season?.id ?? null);
    const matchdayParam = Number(params.get('jornada'));
    const today = new Date().toISOString().slice(0, 10);
    const matchday: Matchday | null =
        matchdays.data?.find((m) => m.id === matchdayParam) ??
        matchdays.data?.find((m) => m.ends_on >= today) ??
        matchdays.data?.[matchdays.data.length - 1] ??
        null;

    const select = (next: { seasonId?: number; matchdayId?: number }) => {
        const updated = new URLSearchParams(params);
        if (next.seasonId !== undefined) {
            updated.set('temporada', String(next.seasonId));
            updated.delete('jornada');
        }
        if (next.matchdayId !== undefined) {
            updated.set('jornada', String(next.matchdayId));
        }
        setParams(updated, { replace: true });
    };

    return {
        seasons: seasons.data ?? [],
        matchdays: matchdays.data ?? [],
        season,
        matchday,
        isPending: seasons.isPending || (season !== null && matchdays.isPending),
        selectSeason: (seasonId: number) => select({ seasonId }),
        selectMatchday: (matchdayId: number) => select({ matchdayId }),
    };
}
