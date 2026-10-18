<?php

namespace App\Services;

use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Team;

/**
 * Suggereix casa o fora per a cada equip a partir de l'últim partit jugat (alternança habitual).
 * No és una regla: el gestor sempre pot triar una altra opció.
 */
class HomeAwaySuggester
{
    /**
     * @return array<int, array{suggestion: string|null, based_on: array{matchday_id: int, match_date: string|null, home_away: string}|null}>
     */
    public function forMatchday(Matchday $matchday): array
    {
        $teamIds = Team::query()->where('is_active', true)->pluck('id');

        $previous = GameMatch::query()
            ->select('matches.*')
            ->join('matchdays', 'matchdays.id', '=', 'matches.matchday_id')
            ->whereIn('matches.team_id', $teamIds)
            ->where('matchdays.starts_on', '<', $matchday->starts_on->toDateString())
            ->whereNotNull('matches.home_away')
            ->where('matches.status', '!=', MatchStatus::Cancelled->value)
            ->orderByDesc('matchdays.starts_on')
            ->orderByDesc('matches.match_date')
            ->orderByDesc('matches.id')
            ->get()
            ->unique('team_id')
            ->keyBy('team_id');

        $suggestions = [];

        foreach ($teamIds as $teamId) {
            $last = $previous->get($teamId);

            $suggestions[$teamId] = $last === null
                ? ['suggestion' => null, 'based_on' => null]
                : [
                    'suggestion' => $last->home_away === HomeAway::Home ? HomeAway::Away->value : HomeAway::Home->value,
                    'based_on' => [
                        'matchday_id' => $last->matchday_id,
                        'match_date' => $last->match_date?->toDateString(),
                        'home_away' => $last->home_away->value,
                    ],
                ];
        }

        return $suggestions;
    }
}
