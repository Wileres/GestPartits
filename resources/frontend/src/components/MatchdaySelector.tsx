import { NativeSelect } from '@/components/NativeSelect';
import { Label } from '@/components/ui/label';
import type { useMatchdaySelection } from '@/hooks/useMatchdaySelection';
import { t } from '@/i18n/ca';

type Selection = ReturnType<typeof useMatchdaySelection>;

export function MatchdaySelector({ selection }: { selection: Selection }) {
    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="grid gap-1.5">
                <Label htmlFor="selector-temporada">{t('matches.season')}</Label>
                <NativeSelect
                    id="selector-temporada"
                    className="min-w-36"
                    value={selection.season?.id ?? ''}
                    onChange={(e) => selection.selectSeason(Number(e.target.value))}
                    disabled={selection.seasons.length === 0}
                >
                    {selection.seasons.map((season) => (
                        <option key={season.id} value={season.id}>
                            {season.name}
                        </option>
                    ))}
                </NativeSelect>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="selector-jornada">{t('matches.matchday')}</Label>
                <NativeSelect
                    id="selector-jornada"
                    className="min-w-64"
                    value={selection.matchday?.id ?? ''}
                    onChange={(e) => selection.selectMatchday(Number(e.target.value))}
                    disabled={selection.matchdays.length === 0}
                >
                    {selection.matchdays.map((matchday) => (
                        <option key={matchday.id} value={matchday.id}>
                            {matchday.display_label}
                        </option>
                    ))}
                </NativeSelect>
            </div>
        </div>
    );
}
