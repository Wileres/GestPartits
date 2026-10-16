<?php

namespace Database\Factories;

use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matchday>
 */
class MatchdayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'starts_on' => '2026-10-17',
            'ends_on' => '2026-10-18',
            'label' => 'Jornada 5',
        ];
    }
}
