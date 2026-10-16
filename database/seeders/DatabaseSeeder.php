<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dades inicials. Es pot executar més d'un cop: cada seeder és idempotent.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            InitialUserSeeder::class,
            CategorySeeder::class,
            VenueSeeder::class,
            TeamSeeder::class,
            SchedulingRuleSeeder::class,
            SeasonSeeder::class,
            WhatsAppTemplateSeeder::class,
            ApplicationSettingSeeder::class,
        ]);
    }
}
