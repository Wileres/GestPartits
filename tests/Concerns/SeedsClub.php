<?php

namespace Tests\Concerns;

use App\Models\FieldArea;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Dades inicials del club (sense l'usuari inicial) i usuaris de prova.
 */
trait SeedsClub
{
    protected function seedClub(): void
    {
        config(['gestpartits.initial_user.password' => null]);
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function manager(): User
    {
        return User::factory()->create();
    }

    protected function team(string $slug): Team
    {
        return Team::query()->with('category')->where('slug', $slug)->firstOrFail();
    }

    protected function area(string $code): FieldArea
    {
        return FieldArea::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Jornada del 17 i 18 d'octubre de 2026 a la temporada actual.
     */
    protected function weekend(string $saturday = '2026-10-17'): Matchday
    {
        $season = Season::query()->where('is_current', true)->firstOrFail();
        $sunday = date('Y-m-d', strtotime($saturday.' +1 day'));

        return Matchday::query()->firstOrCreate(['season_id' => $season->id, 'starts_on' => $saturday], ['ends_on' => $sunday]);
    }
}
