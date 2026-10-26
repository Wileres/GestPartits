import { CalendarDays, ImageIcon, Lightbulb, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { QuickEntryRow } from '@/api/matches';
import type { HomeAway, HomeAwaySuggestion, Match, Matchday, ReferenceData, Team } from '@/api/types';
import { HomeAwayBadge, LockedBadge, StatusBadge } from '@/components/MatchBadges';
import { NativeSelect } from '@/components/NativeSelect';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/QueryState';
import { MatchdaySelector } from '@/components/MatchdaySelector';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { TimeTextInput } from '@/components/TimeFields';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ResultImagesDialog } from '@/features/images/ResultImagesDialog';
import { useTeams } from '@/features/teams/useTeams';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useReferenceData } from '@/hooks/useReferenceData';
import { cn } from '@/lib/utils';
import { useMatchdaySelection } from '@/hooks/useMatchdaySelection';
import { ca, t } from '@/i18n/ca';
import { formatWeekdayDate, matchdayDates } from '@/lib/dates';
import { emptyToNull, errorMessage } from '@/lib/errors';
import { isTime } from '@/lib/time';
import { MatchSheet, type MatchSheetTarget } from './MatchSheet';
import { useCreateMatches, useHomeAwaySuggestions, useMatches } from './useMatches';

export function MatchesPage() {
    const selection = useMatchdaySelection();
    const { data: reference } = useReferenceData();
    const [resultsOpen, setResultsOpen] = useState(false);

    return (
        <>
            <PageHeader
                title={t('matches.title')}
                description={t('matches.subtitle')}
                actions={
                    selection.matchday !== null && (
                        <Button variant="outline" onClick={() => setResultsOpen(true)}>
                            <ImageIcon aria-hidden />
                            {t('images.results')}
                        </Button>
                    )
                }
            />
            {selection.matchday !== null && <ResultImagesDialog matchdayId={selection.matchday.id} open={resultsOpen} onClose={() => setResultsOpen(false)} />}
            <div className="mb-6">
                <MatchdaySelector selection={selection} />
            </div>
            {selection.isPending || reference === undefined ? (
                <LoadingState />
            ) : selection.season === null ? (
                <EmptyState title={t('matches.noSeason')} />
            ) : selection.matchday === null ? (
                <EmptyState title={t('matches.noMatchday')} />
            ) : (
                <MatchdayMatches key={selection.matchday.id} matchday={selection.matchday} reference={reference} />
            )}
        </>
    );
}

type Choice = '' | HomeAway;

/** Els partits a casa es ressalten amb un blau molt suau (a la taula i a les targetes del mòbil). */
const HOME_ROW = 'bg-primary/[0.06] hover:bg-primary/10';
const HOME_CARD = 'border-primary/25 bg-primary/[0.05]';

interface Draft {
    choice: Choice;
    opponent: string;
    /** Dia i hora d'un partit a fora (els partits a casa carreguen l'horari preferent de l'equip). */
    date: string;
    time: string;
}

const emptyDraft = (choice: Choice): Draft => ({ choice, opponent: '', date: '', time: '' });

/** L'hora d'un partit a fora ha de ser vàlida i anar acompanyada del dia. */
const isDraftInvalid = (draft: Draft): boolean => draft.choice === 'away' && draft.time !== '' && (!isTime(draft.time) || draft.date === '');

function MatchdayMatches({ matchday, reference }: { matchday: Matchday; reference: ReferenceData }) {
    const teams = useTeams();
    const matches = useMatches(matchday.id);
    const suggestions = useHomeAwaySuggestions(matchday.id);
    const createMatches = useCreateMatches(matchday.id);
    const [drafts, setDrafts] = useState<Record<number, Draft>>({});
    const [sheet, setSheet] = useState<MatchSheetTarget | null>(null);
    const wide = useMediaQuery('(min-width: 768px)');

    if (teams.isPending || matches.isPending) return <LoadingState />;
    if (teams.isError || matches.isError) return <ErrorState />;

    const matchesByTeam = new Map<number, Match[]>();
    for (const match of matches.data) {
        matchesByTeam.set(match.team_id, [...(matchesByTeam.get(match.team_id) ?? []), match]);
    }

    // Equips actius i, a més, els desactivats que ja tenen partit en aquesta jornada.
    const rows = teams.data.filter((team) => team.is_active || matchesByTeam.has(team.id));

    const draftFor = (team: Team): Draft => {
        const suggestion = suggestions.data?.[String(team.id)]?.suggestion ?? null;

        return drafts[team.id] ?? emptyDraft(suggestion ?? '');
    };

    const pendingRows: QuickEntryRow[] = rows
        .filter((team) => !matchesByTeam.has(team.id) && draftFor(team).choice !== '')
        .map((team) => {
            const draft = draftFor(team);

            return {
                team_id: team.id,
                home_away: draft.choice === '' ? null : draft.choice,
                opponent: emptyToNull(draft.opponent),
                round_label: null,
                match_date: draft.choice === 'away' ? emptyToNull(draft.date) : null,
                kickoff_time: draft.choice === 'away' ? emptyToNull(draft.time) : null,
            };
        });
    const hasInvalidDraft = rows.some((team) => !matchesByTeam.has(team.id) && isDraftInvalid(draftFor(team)));

    const update = (team: Team, patch: Partial<Draft>) => setDrafts((current) => ({ ...current, [team.id]: { ...draftFor(team), ...patch } }));

    const submit = () =>
        createMatches.mutate(pendingRows, {
            onSuccess: (created) => {
                toast.success(created.length === 1 ? t('matches.createdOne') : t('matches.created', { count: created.length }));
                setDrafts({});
            },
            onError: (error) => toast.error(errorMessage(error)),
        });

    return (
        <>
            {!wide && (
                <ul className="grid gap-3">
                    {rows.map((team) => (
                        <TeamCard
                            key={team.id}
                            team={team}
                            matches={matchesByTeam.get(team.id) ?? []}
                            draft={draftFor(team)}
                            matchday={matchday}
                            suggestion={suggestions.data?.[String(team.id)]}
                            onChange={(patch) => update(team, patch)}
                            onEdit={(match) => setSheet({ team, match, suggestedHomeAway: suggestions.data?.[String(team.id)]?.suggestion ?? null })}
                        />
                    ))}
                </ul>
            )}
            {wide && (
            <Card className="overflow-x-auto py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="min-w-36">{t('matches.team')}</TableHead>
                            <TableHead className="min-w-36">{t('matches.condition')}</TableHead>
                            <TableHead className="min-w-48">{t('matches.opponent')}</TableHead>
                            <TableHead className="min-w-44">{t('matches.when')}</TableHead>
                            <TableHead className="text-right">{t('common.actions')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((team) => {
                            const teamMatches = matchesByTeam.get(team.id) ?? [];
                            const suggestion = suggestions.data?.[String(team.id)];

                            if (teamMatches.length === 0) {
                                return <DraftRow key={team.id} team={team} draft={draftFor(team)} matchday={matchday} suggestion={suggestion} onChange={(patch) => update(team, patch)} />;
                            }

                            return teamMatches.map((match, index) => (
                                <TableRow key={match.id} className={cn(match.home_away === 'home' && HOME_ROW)}>
                                    <TableCell className="font-medium">
                                        {index === 0 && <TeamCell team={team} />}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {match.home_away !== null && <HomeAwayBadge value={match.home_away} />}
                                            <StatusBadge status={match.status} />
                                            {match.is_locked && <LockedBadge />}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {match.opponent ?? t('common.none')}
                                        <ResultBadge match={match} team={team} />
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        <MatchWhen match={match} />
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <Button variant="ghost" size="icon" onClick={() => setSheet({ team, match })} aria-label={`${t('matches.edit')}: ${team.name}`}>
                                            <Pencil aria-hidden />
                                        </Button>
                                        {index === teamMatches.length - 1 && (
                                            <Button variant="ghost" size="icon" onClick={() => setSheet({ team, match: null, suggestedHomeAway: suggestions.data?.[String(team.id)]?.suggestion ?? null })} aria-label={`${t('matches.add')}: ${team.name}`}>
                                                <Plus aria-hidden />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ));
                        })}
                    </TableBody>
                </Table>
            </Card>
            )}

            <div className="sticky bottom-0 mt-4 flex justify-end bg-gradient-to-t from-background via-background to-transparent py-3">
                <Button onClick={submit} disabled={pendingRows.length === 0 || hasInvalidDraft || createMatches.isPending}>
                    <CalendarDays aria-hidden />
                    {t('matches.createSelected', { count: pendingRows.length })}
                </Button>
            </div>

            <MatchSheet target={sheet} matchday={matchday} reference={reference} onClose={() => setSheet(null)} />
        </>
    );
}

interface TeamCardProps {
    team: Team;
    matches: Match[];
    draft: Draft;
    matchday: Matchday;
    suggestion: HomeAwaySuggestion | undefined;
    onChange: (patch: Partial<Draft>) => void;
    onEdit: (match: Match | null) => void;
}

/** Versió per a mòbil del full ràpid: una targeta per equip. */
function TeamCard({ team, matches, draft, matchday, suggestion, onChange, onEdit }: TeamCardProps) {
    return (
        <li>
            <Card className={cn('gap-3 px-4 py-3', (matches.some((m) => m.home_away === 'home') || (matches.length === 0 && draft.choice === 'home')) && HOME_CARD)}>
                <div className="flex items-start justify-between gap-2">
                    <TeamCell team={team} />
                    <Button variant="ghost" size="icon" onClick={() => onEdit(null)} aria-label={`${t('matches.add')}: ${team.name}`}>
                        <Plus aria-hidden />
                    </Button>
                </div>
                {matches.length === 0 ? (
                    <>
                        <DraftControls team={team} draft={draft} matchday={matchday} suggestion={suggestion} onChange={onChange} />
                        <DraftWhen team={team} draft={draft} matchday={matchday} onChange={onChange} />
                    </>
                ) : (
                    matches.map((match) => (
                        <button
                            key={match.id}
                            type="button"
                            onClick={() => onEdit(match)}
                            className="flex w-full items-center justify-between gap-2 rounded-lg border bg-background p-2 text-left"
                            aria-label={`${t('matches.edit')}: ${team.name}`}
                        >
                            <span className="grid gap-1">
                                <span className="flex flex-wrap gap-1">
                                    {match.home_away !== null && <HomeAwayBadge value={match.home_away} />}
                                    <StatusBadge status={match.status} />
                                    {match.is_locked && <LockedBadge />}
                                </span>
                                <span className="text-sm">
                                    {match.opponent ?? t('common.none')}
                                    <ResultBadge match={match} team={team} />
                                </span>
                            </span>
                            <span className="text-right text-sm">
                                <MatchWhen match={match} />
                            </span>
                        </button>
                    ))
                )}
            </Card>
        </li>
    );
}

function TeamCell({ team }: { team: Team }) {
    return (
        <span className="flex items-center gap-2">
            <span className="size-3 shrink-0 rounded-full border" style={{ backgroundColor: team.color ?? 'var(--secondary)' }} aria-hidden />
            <span>
                {team.name}
                <span className="block text-xs font-normal text-muted-foreground">{team.category.name}</span>
            </span>
            {!team.is_active && <Badge variant="outline">{t('matches.inactiveTeam')}</Badge>}
        </span>
    );
}

/** Resultat del partit (gols de l'equip - gols del rival), si ja s'ha desat. */
function ResultBadge({ match, team }: { match: Match; team: Team }) {
    if (!match.has_result) return null;

    return (
        <Badge variant="secondary" className="ml-2 tabular-nums" aria-label={t('matches.resultLabel', { team: team.name, goalsTeam: match.score_team ?? 0, goalsOpponent: match.score_opponent ?? 0 })}>
            {match.score_team} - {match.score_opponent}
        </Badge>
    );
}

function MatchWhen({ match }: { match: Match }) {
    if (match.match_date === null || match.weekday === null) return <span className="text-muted-foreground">{t('matches.unscheduled')}</span>;

    return (
        <span>
            {ca.matches.weekdayShort[match.weekday - 1]} {match.kickoff_time ?? ''}
            {match.field_area !== null && match.field_area !== undefined && <span className="block text-xs text-muted-foreground">{match.field_area.name}</span>}
            {match.home_away === 'away' && match.municipality !== null && <span className="block text-xs text-muted-foreground">{match.municipality}</span>}
        </span>
    );
}

interface DraftRowProps {
    team: Team;
    draft: Draft;
    matchday: Matchday;
    suggestion: HomeAwaySuggestion | undefined;
    onChange: (patch: Partial<Draft>) => void;
}

function DraftRow(props: DraftRowProps) {
    return (
        <TableRow className={cn('bg-muted/30', props.draft.choice === 'home' && HOME_ROW)}>
            <TableCell className="font-medium">
                <TeamCell team={props.team} />
            </TableCell>
            <DraftControls {...props} cell />
            <TableCell className="text-sm">
                <DraftWhen team={props.team} draft={props.draft} matchday={props.matchday} onChange={props.onChange} cell />
            </TableCell>
            <TableCell />
        </TableRow>
    );
}

/** Condició i rival d'un equip que encara no té partit (a la taula, com a dues cel·les). */
function DraftControls({ team, draft, suggestion, onChange, cell = false }: DraftRowProps & { cell?: boolean }) {
    const conditionId = `quick-condition-${team.id}`;
    const opponentId = `quick-opponent-${team.id}`;
    const suggested = suggestion?.suggestion ?? null;
    const Wrapper = cell ? TableCell : 'div';

    return (
        <>
            <Wrapper>
                <label htmlFor={conditionId} className={cell ? 'sr-only' : 'mb-1 block text-sm font-medium'}>
                    {cell ? `${t('matches.condition')} · ${team.name}` : t('matches.condition')}
                </label>
                <NativeSelect id={conditionId} value={draft.choice} onChange={(e) => {
                        const choice = e.target.value as Choice;
                        onChange(choice === 'away' ? { choice } : { choice, date: '', time: '' });
                    }}>
                    <option value="">{t('matches.notEntered')}</option>
                    <option value="home">{t('labels.homeAway.home')}</option>
                    <option value="away">{t('labels.homeAway.away')}</option>
                </NativeSelect>
                {suggested !== null && suggestion?.based_on != null && (
                    <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground" title={t('matches.suggestedHelp', { value: t(`labels.homeAway.${suggestion.based_on.home_away}`).toLowerCase() })}>
                        <Lightbulb className="size-3" aria-hidden />
                        {t('matches.suggested', { value: t(`labels.homeAwayShort.${suggested}`) })}
                    </p>
                )}
            </Wrapper>
            <Wrapper>
                <label htmlFor={opponentId} className={cell ? 'sr-only' : 'mb-1 block text-sm font-medium'}>
                    {cell ? `${t('matches.opponent')} · ${team.name}` : t('matches.opponent')}
                </label>
                <Input id={opponentId} value={draft.opponent} onChange={(e) => onChange({ opponent: e.target.value })} disabled={draft.choice === ''} />
            </Wrapper>
        </>
    );
}

interface DraftWhenProps {
    team: Team;
    draft: Draft;
    matchday: Matchday;
    onChange: (patch: Partial<Draft>) => void;
    /** A la taula el text de les etiquetes només el veuen els lectors de pantalla. */
    cell?: boolean;
}

/** Dia i hora d'un partit a fora, directament al full ràpid; els partits a casa es col·loquen sols. */
function DraftWhen({ team, draft, matchday, onChange, cell = false }: DraftWhenProps) {
    if (draft.choice !== 'away') {
        return cell ? <span className="text-muted-foreground">{t('matches.unscheduled')}</span> : null;
    }

    const dateId = `quick-date-${team.id}`;
    const timeId = `quick-time-${team.id}`;
    const label = cell ? 'sr-only' : 'mb-1 block text-sm font-medium';
    const invalid = isDraftInvalid(draft);

    return (
        <div className={cell ? 'flex items-center gap-2' : 'grid grid-cols-2 gap-2'}>
            <div>
                <label htmlFor={dateId} className={label}>
                    {cell ? `${t('matches.date')} · ${team.name}` : t('matches.date')}
                </label>
                <NativeSelect id={dateId} value={draft.date} onChange={(e) => onChange({ date: e.target.value })} aria-invalid={invalid && draft.date === ''}>
                    <option value="">{t('matches.unscheduled')}</option>
                    {matchdayDates(matchday).map((date) => (
                        <option key={date} value={date}>
                            {formatWeekdayDate(date)}
                        </option>
                    ))}
                </NativeSelect>
            </div>
            <div className={cell ? 'w-24' : undefined}>
                <label htmlFor={timeId} className={label}>
                    {cell ? `${t('matches.kickoff')} · ${team.name}` : t('matches.kickoff')}
                </label>
                <TimeTextInput id={timeId} value={draft.time} onChange={(e) => onChange({ time: e.target.value })} aria-invalid={invalid && !isTime(draft.time)} />
            </div>
        </div>
    );
}
