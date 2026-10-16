<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2000, 2099);

        return [
            'name' => $year.'-'.($year + 1),
            'starts_on' => "{$year}-09-01",
            'ends_on' => ($year + 1).'-06-30',
            'is_current' => false,
        ];
    }
}
