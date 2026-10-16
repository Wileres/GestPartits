<?php

namespace Database\Seeders;

use App\Enums\GameFormat;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Categories del club i modalitat.
 * Les durades (minuts, descans inclòs) són VALORS DE DEMOSTRACIÓ: cal revisar-les a Configuració.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'escoleta', 'name' => 'Escoleta', 'game_format' => GameFormat::Football7, 'match_duration_min' => 45],
            ['slug' => 'prebenjami', 'name' => 'Prebenjamí', 'game_format' => GameFormat::Football7, 'match_duration_min' => 50],
            ['slug' => 'benjami', 'name' => 'Benjamí', 'game_format' => GameFormat::Football7, 'match_duration_min' => 55],
            ['slug' => 'alevi', 'name' => 'Aleví', 'game_format' => GameFormat::Football7, 'match_duration_min' => 60],
            ['slug' => 'infantil', 'name' => 'Infantil', 'game_format' => GameFormat::Football11, 'match_duration_min' => 75],
            ['slug' => 'cadet', 'name' => 'Cadet', 'game_format' => GameFormat::Football11, 'match_duration_min' => 85],
            ['slug' => 'juvenil', 'name' => 'Juvenil', 'game_format' => GameFormat::Football11, 'match_duration_min' => 95],
            ['slug' => 'primer-equip', 'name' => 'Primer equip', 'game_format' => GameFormat::Football11, 'match_duration_min' => 105],
        ];

        foreach ($categories as $order => $category) {
            Category::query()->firstOrCreate(['slug' => $category['slug']], [
                ...$category,
                'post_min' => 10,
                'min_gap_min' => 0,
                'sort_order' => ($order + 1) * 10,
                'is_active' => true,
            ]);
        }
    }
}
