import { CalendarPlus, Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { Matchday, Season } from '@/api/types';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/i18n/ca';
import { formatDate } from '@/lib/dates';
import { errorMessage } from '@/lib/errors';
import { cn } from '@/lib/utils';
import { MatchdayFormDialog } from './MatchdayFormDialog';
import { SeasonFormDialog } from './SeasonFormDialog';
import { useDeleteMatchday, useDeleteSeason, useMatchdays, useSeasons } from './useSeasons';

export function SeasonsPage() {
    const { data: seasons, isPending, isError } = useSeasons();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [seasonForm, setSeasonForm] = useState<{ open: boolean; season: Season | null }>({ open: false, season: null });
    const [deletingSeason, setDeletingSeason] = useState<Season | null>(null);
    const deleteSeason = useDeleteSeason();

    const selected = seasons?.find((s) => s.id === selectedId) ?? seasons?.find((s) => s.is_current) ?? seasons?.[0] ?? null;

    return (
        <>
            <PageHeader
                title={t('seasons.title')}
                description={t('seasons.subtitle')}
                actions={
                    <Button onClick={() => setSeasonForm({ open: true, season: null })}>
                        <Plus aria-hidden />
                        {t('seasons.create')}
                    </Button>
                }
            />
            {isError && <ErrorState />}
            {isPending ? (
                <LoadingState />
            ) : (
                <div className="grid gap-6 lg:grid-cols-[18rem_1fr]">
                    <ul className="grid content-start gap-2">
                        {seasons?.map((season) => (
                            <li key={season.id}>
                                <Card
                                    className={cn('flex-row items-center gap-2 px-3 py-3 transition-colors', season.id === selected?.id && 'border-primary ring-2 ring-primary/20')}
                                >
                                    <button type="button" className="min-w-0 flex-1 text-left" onClick={() => setSelectedId(season.id)} aria-pressed={season.id === selected?.id}>
                                        <span className="flex items-center gap-2 font-semibold">
                                            {season.name}
                                            {season.is_current && (
                                                <Badge className="gap-1">
                                                    <Star className="size-3" aria-hidden />
                                                    {t('seasons.current')}
                                                </Badge>
                                            )}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {formatDate(season.starts_on)} – {formatDate(season.ends_on)}
                                        </span>
                                    </button>
                                    <Button variant="ghost" size="icon" onClick={() => setSeasonForm({ open: true, season })} aria-label={`${t('common.edit')}: ${season.name}`}>
                                        <Pencil aria-hidden />
                                    </Button>
                                    <Button variant="ghost" size="icon" onClick={() => setDeletingSeason(season)} aria-label={`${t('common.delete')}: ${season.name}`}>
                                        <Trash2 className="text-destructive" aria-hidden />
                                    </Button>
                                </Card>
                            </li>
                        ))}
                    </ul>
                    {selected !== null && <SeasonMatchdays season={selected} />}
                </div>
            )}

            <SeasonFormDialog open={seasonForm.open} season={seasonForm.season} onOpenChange={(open) => setSeasonForm((s) => ({ ...s, open }))} />
            <ConfirmDialog
                open={deletingSeason !== null}
                title={t('seasons.deleteSeasonConfirmTitle', { name: deletingSeason?.name ?? '' })}
                pending={deleteSeason.isPending}
                onCancel={() => setDeletingSeason(null)}
                onConfirm={() =>
                    deletingSeason !== null &&
                    deleteSeason.mutate(deletingSeason.id, {
                        onSuccess: () => {
                            toast.success(t('seasons.deleted'));
                            setDeletingSeason(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </>
    );
}

function SeasonMatchdays({ season }: { season: Season }) {
    const { data: matchdays, isPending } = useMatchdays(season.id);
    const [form, setForm] = useState<{ open: boolean; matchday: Matchday | null }>({ open: false, matchday: null });
    const [deleting, setDeleting] = useState<Matchday | null>(null);
    const deleteMatchday = useDeleteMatchday(season.id);

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                <CardTitle>
                    <h2>
                        {t('seasons.matchdays')} · {season.name}
                    </h2>
                </CardTitle>
                <Button size="sm" onClick={() => setForm({ open: true, matchday: null })}>
                    <CalendarPlus aria-hidden />
                    {t('seasons.createMatchday')}
                </Button>
            </CardHeader>
            <CardContent>
                {isPending ? (
                    <LoadingState />
                ) : matchdays === undefined || matchdays.length === 0 ? (
                    <EmptyState title={t('seasons.noMatchdays')} />
                ) : (
                    <ul className="divide-y">
                        {matchdays.map((matchday) => (
                            <li key={matchday.id} className="flex flex-wrap items-center gap-2 py-2">
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">{matchday.display_label}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDate(matchday.starts_on)} – {formatDate(matchday.ends_on)} · {(matchday.matches_count === 1 ? t('seasons.matchesOne') : t('seasons.matches', { count: matchday.matches_count ?? 0 }))}
                                    </p>
                                </div>
                                <Button variant="ghost" size="icon" onClick={() => setForm({ open: true, matchday })} aria-label={`${t('common.edit')}: ${matchday.display_label}`}>
                                    <Pencil aria-hidden />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => setDeleting(matchday)} aria-label={`${t('common.delete')}: ${matchday.display_label}`}>
                                    <Trash2 className="text-destructive" aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            <MatchdayFormDialog open={form.open} seasonId={season.id} matchday={form.matchday} onOpenChange={(open) => setForm((f) => ({ ...f, open }))} />
            <ConfirmDialog
                open={deleting !== null}
                title={t('seasons.deleteMatchdayConfirmTitle', { name: deleting?.display_label ?? '' })}
                pending={deleteMatchday.isPending}
                onCancel={() => setDeleting(null)}
                onConfirm={() =>
                    deleting !== null &&
                    deleteMatchday.mutate(deleting.id, {
                        onSuccess: () => {
                            toast.success(t('seasons.deleted'));
                            setDeleting(null);
                        },
                        onError: (error) => toast.error(errorMessage(error)),
                    })
                }
            />
        </Card>
    );
}
