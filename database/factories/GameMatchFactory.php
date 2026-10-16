<?php

namespace Database\Factories;

use App\Enums\GameFormat;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameMatch>
 */
class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matchday_id' => Matchday::factory(),
            'team_id' => Team::factory(),
            'opponent' => 'CF '.fake()->city(),
            'home_away' => HomeAway::Home,
            'status' => MatchStatus::Pending,
            'game_format' => GameFormat::Football7,
            'duration_min' => 60,
            'post_min' => 10,
            'is_locked' => false,
            'show_in_image' => true,
        ];
    }

    public function away(): static
    {
        return $this->state(fn () => [
            'home_away' => HomeAway::Away,
            'venue_name' => 'Camp Municipal',
            'municipality' => fake()->city(),
        ]);
    }
}
