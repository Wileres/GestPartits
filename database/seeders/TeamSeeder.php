<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Equips d'exemple. El gestor en pot crear, editar, ordenar i desactivar més.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['escoleta', 'Escoleta A'],
            ['escoleta', 'Escoleta B'],
            ['benjami', 'Benjamí A'],
            ['benjami', 'Benjamí B'],
            ['benjami', 'Benjamí C'],
            ['alevi', 'Aleví A'],
            ['infantil', 'Infantil A'],
            ['cadet', 'Cadet A'],
            ['juvenil', 'Juvenil A'],
            ['primer-equip', 'Primer equip'],
        ];

        $categories = Category::query()->pluck('id', 'slug');

        foreach ($teams as $order => [$categorySlug, $name]) {
            Team::query()->firstOrCreate(['slug' => Str::slug($name)], [
                'category_id' => $categories[$categorySlug],
                'name' => $name,
                'sort_order' => ($order + 1) * 10,
                'is_active' => true,
            ]);
        }
    }
}
