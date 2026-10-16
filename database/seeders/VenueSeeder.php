<?php

namespace Database\Seeders;

use App\Enums\FieldAreaKind;
use App\Models\AvailabilitySlot;
use App\Models\FieldArea;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Camp del club, espais (Camp 1, Camp 2, Camp complet) i franges horàries setmanals.
 */
class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::query()->firstOrCreate(
            ['is_home' => true],
            ['name' => 'Camp del Joventut Sant Pere Màrtir'],
        );

        $areas = [
            ['code' => FieldArea::CODE_CAMP_1, 'name' => 'Camp 1', 'kind' => FieldAreaKind::Half, 'sort_order' => 1],
            ['code' => FieldArea::CODE_CAMP_2, 'name' => 'Camp 2', 'kind' => FieldAreaKind::Half, 'sort_order' => 2],
            ['code' => FieldArea::CODE_FULL, 'name' => 'Camp complet', 'kind' => FieldAreaKind::Full, 'sort_order' => 3],
        ];

        foreach ($areas as $area) {
            FieldArea::query()->firstOrCreate(['venue_id' => $venue->id, 'code' => $area['code']], $area);
        }

        // Les franges limiten l'hora d'inici. default_fill_order: dissabte matí → diumenge matí.
        $slots = [
            ['weekday' => 6, 'label' => 'Dissabte matí', 'opens_at' => '09:00', 'last_kickoff_at' => '13:00', 'default_fill_order' => 1],
            ['weekday' => 6, 'label' => 'Dissabte tarda', 'opens_at' => '17:00', 'last_kickoff_at' => '19:00', 'default_fill_order' => null],
            ['weekday' => 7, 'label' => 'Diumenge matí', 'opens_at' => '09:00', 'last_kickoff_at' => '13:00', 'default_fill_order' => 2],
        ];

        foreach ($slots as $slot) {
            AvailabilitySlot::query()->firstOrCreate(
                ['venue_id' => $venue->id, 'weekday' => $slot['weekday'], 'label' => $slot['label']],
                [...$slot, 'is_active' => true],
            );
        }
    }
}
