<?php

namespace Database\Factories;

use App\Enums\GameFormat;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Categoria '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'game_format' => GameFormat::Football7,
            'match_duration_min' => 60,
            'post_min' => 10,
            'min_gap_min' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function football11(): static
    {
        return $this->state(fn () => ['game_format' => GameFormat::Football11, 'match_duration_min' => 105]);
    }
}
