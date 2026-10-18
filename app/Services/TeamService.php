<?php

namespace App\Services;

use App\Enums\RuleStrength;
use App\Models\FieldArea;
use App\Models\SchedulingRule;
use App\Models\Team;
use App\Services\Scheduling\PreferredScheduleService;
use App\Services\Scheduling\ScheduleInputFactory;
use App\Support\ClubSettings;
use App\Support\Time;
use App\Support\UniqueSlug;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function __construct(
        private readonly PreferredScheduleService $preferred,
        private readonly ScheduleInputFactory $inputs,
        private readonly ClubSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Pot incloure «preferred_schedule» (horari preferent a casa).
     */
    public function create(array $data): Team
    {
        return DB::transaction(function () use ($data): Team {
            $hasPreference = array_key_exists('preferred_schedule', $data);
            $preference = $data['preferred_schedule'] ?? null;
            unset($data['preferred_schedule']);

            $data['slug'] = UniqueSlug::for(Team::class, $data['name']);
            $data['sort_order'] = (int) Team::query()->max('sort_order') + 10;

            $team = Team::query()->create($data);

            if ($hasPreference) {
                $this->syncPreferredSchedule($team, $preference);
            }

            return $team->load('category');
        });
    }

    /**
     * @param  array<string, mixed>  $data  Si hi ha «preferred_schedule» (fins i tot null), es desa l'horari preferent.
     */
    public function update(Team $team, array $data): Team
    {
        return DB::transaction(function () use ($team, $data): Team {
            $hasPreference = array_key_exists('preferred_schedule', $data);
            $preference = $data['preferred_schedule'] ?? null;
            unset($data['preferred_schedule']);

            if ($data['name'] !== $team->name) {
                $data['slug'] = UniqueSlug::for(Team::class, $data['name'], $team->id);
            }

            $team->update($data);

            if ($hasPreference) {
                $this->syncPreferredSchedule($team, $preference);
            }

            return $team->load('category');
        });
    }

    /**
     * @param  array<int, int>  $teamIds  Ordre nou (els equips no inclosos van al final).
     */
    public function reorder(array $teamIds): void
    {
        DB::transaction(function () use ($teamIds): void {
            $position = 0;
            foreach ($teamIds as $id) {
                Team::query()->whereKey($id)->update(['sort_order' => ++$position * 10]);
            }

            $remaining = Team::query()->whereNotIn('id', $teamIds)->orderBy('sort_order')->orderBy('id')->get();
            foreach ($remaining as $team) {
                $team->updateQuietly(['sort_order' => ++$position * 10]);
            }
        });
    }

    /**
     * Desa l'horari preferent a casa d'un equip com la seva regla de preferència (equip > categoria > modalitat).
     * Es reaprofita la regla de l'equip si ja en té una (conserva el tipus i la prioritat); null l'esborra.
     *
     * @param  array{weekday: int, time: string, field_area_id: int|null}|null  $preference
     */
    public function syncPreferredSchedule(Team $team, ?array $preference): void
    {
        $rule = SchedulingRule::query()->where('team_id', $team->id)->whereNotNull('preferred_time')->orderBy('id')->first();

        if ($preference === null) {
            $rule?->delete();

            return;
        }

        $attributes = [
            'weekday' => $preference['weekday'],
            'preferred_time' => $preference['time'],
            'preferred_field_area_id' => $preference['field_area_id'] ?? null,
            'is_active' => true,
        ];

        if ($rule !== null) {
            $rule->update($attributes);

            return;
        }

        SchedulingRule::query()->create([
            ...$attributes,
            'team_id' => $team->id,
            'priority' => 50,
            'strength' => RuleStrength::Recommended,
            'notes' => "Horari preferent de {$team->name}",
        ]);
    }

    /**
     * Afegeix a cada equip el seu horari preferent propi i, si no en té, l'heretat de la categoria o la modalitat.
     * (Atributs no persistits que llegeix TeamResource.)
     *
     * @param  Team|Collection<int, Team>  $teams
     */
    public function decorate(Team|Collection $teams): void
    {
        $list = $teams instanceof Team ? collect([$teams]) : $teams;
        $rules = $this->inputs->rules();
        $areas = FieldArea::query()->where('venue_id', $this->settings->homeVenue()->id)->pluck('id', 'code');

        foreach ($list as $team) {
            $team->loadMissing('category');
            $rule = $this->preferred->ruleFor($team, null, $rules);

            $summary = $rule === null ? null : [
                'weekday' => $rule->weekday,
                'time' => $rule->preferred === null ? null : Time::fromMinutes($rule->preferred),
                'field_area_id' => $rule->area === null ? null : ($areas[$rule->area] ?? null),
                'source' => $rule->teamId !== null ? 'team' : ($rule->categoryId !== null ? 'category' : 'format'),
            ];

            $team->setAttribute('preferred_schedule', $summary !== null && $summary['source'] === 'team' ? $summary : null);
            $team->setAttribute('inherited_preference', $summary !== null && $summary['source'] !== 'team' ? $summary : null);
        }
    }
}
