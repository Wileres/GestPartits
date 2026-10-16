<?php

namespace Database\Seeders;

use App\Enums\RuleStrength;
use App\Models\Category;
use App\Models\FieldArea;
use App\Models\SchedulingRule;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Regles horàries inicials. Es poden modificar o desactivar des de Configuració.
 */
class SchedulingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $team = fn (string $slug): int => Team::query()->where('slug', $slug)->value('id');
        $category = fn (string $slug): int => Category::query()->where('slug', $slug)->value('id');

        $areas = FieldArea::query()->where('venue_id', Venue::home()->id)->pluck('id', 'code');

        $rules = [
            [
                'key' => ['team_id' => $team('primer-equip')],
                'weekday' => 6, 'preferred_time' => '17:00', 'priority' => 100,
                'strength' => RuleStrength::Recommended, 'notes' => 'Primer equip: dissabte a les 17:00',
            ],
            [
                'key' => ['team_id' => $team('benjami-a')],
                'weekday' => 7, 'preferred_time' => '12:30', 'priority' => 80,
                'preferred_field_area_id' => $areas[FieldArea::CODE_CAMP_1],
                'strength' => RuleStrength::Recommended, 'notes' => 'Benjamí A: diumenge a les 12:30',
            ],
            [
                'key' => ['team_id' => $team('benjami-b')],
                'weekday' => 7, 'preferred_time' => '12:30', 'priority' => 80,
                'preferred_field_area_id' => $areas[FieldArea::CODE_CAMP_2],
                'strength' => RuleStrength::Recommended, 'notes' => 'Benjamí B: diumenge a les 12:30',
            ],
            [
                'key' => ['team_id' => $team('benjami-c')],
                'weekday' => 6, 'preferred_time' => '12:30', 'priority' => 80,
                'strength' => RuleStrength::Recommended, 'notes' => 'Benjamí C: dissabte a les 12:30',
            ],
            [
                'key' => ['category_id' => $category('alevi')],
                'weekday' => 6, 'preferred_time' => '11:15', 'priority' => 70,
                'strength' => RuleStrength::Recommended, 'notes' => 'Equips aleví: dissabte a les 11:15',
            ],
            [
                'key' => ['category_id' => $category('escoleta')],
                'not_before' => '10:00', 'priority' => 90,
                'strength' => RuleStrength::Mandatory, 'notes' => 'Escoleta: mai abans de les 10:00',
            ],
        ];

        foreach ($rules as $rule) {
            $key = $rule['key'];
            unset($rule['key']);

            SchedulingRule::query()->firstOrCreate([...$key, 'notes' => $rule['notes']], [...$key, ...$rule, 'is_active' => true]);
        }
    }
}
