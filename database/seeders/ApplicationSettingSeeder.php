<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Configuració general i identitat visual (blau i blanc).
 */
class ApplicationSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'club_name' => 'Joventut Sant Pere Màrtir',
            'home_venue_id' => Venue::home()->id,
            // Horari en què el gestor pot col·locar partits manualment (limita l'hora d'inici).
            'operating_hours' => ['first_kickoff' => '09:00', 'last_kickoff' => '21:00'],
            'kickoff_step_min' => 15,
            'branding' => [
                'primary_color' => '#0B4EA2',
                'primary_dark_color' => '#082F66',
                'secondary_color' => '#FFFFFF',
                'logo_path' => null,
            ],
            // Avís a Configuració fins que algú revisi les durades de demostració.
            'demo_durations_pending_review' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (! ApplicationSetting::query()->where('key', $key)->exists()) {
                ApplicationSetting::put($key, $value);
            }
        }
    }
}
