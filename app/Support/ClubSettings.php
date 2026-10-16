<?php

namespace App\Support;

use App\Models\ApplicationSetting;
use App\Models\Venue;

/**
 * Accés tipat a la configuració general (application_settings).
 */
class ClubSettings
{
    public function clubName(): string
    {
        return (string) ApplicationSetting::get('club_name', 'Joventut Sant Pere Màrtir');
    }

    public function kickoffStepMin(): int
    {
        return (int) ApplicationSetting::get('kickoff_step_min', 15);
    }

    /**
     * Horari en què es pot fixar l'inici d'un partit a casa.
     *
     * @return array{first_kickoff: string, last_kickoff: string}
     */
    public function operatingHours(): array
    {
        $value = ApplicationSetting::get('operating_hours', []);

        return [
            'first_kickoff' => (string) ($value['first_kickoff'] ?? '09:00'),
            'last_kickoff' => (string) ($value['last_kickoff'] ?? '21:00'),
        ];
    }

    public function demoDurationsPendingReview(): bool
    {
        return (bool) ApplicationSetting::get('demo_durations_pending_review', false);
    }

    public function homeVenue(): Venue
    {
        $id = ApplicationSetting::get('home_venue_id');

        return $id === null ? Venue::home() : Venue::query()->findOrFail($id);
    }
}
