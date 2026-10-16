<?php

namespace App\Services;

use App\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeasonService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Season $season, array $data): Season
    {
        return DB::transaction(function () use ($season, $data): Season {
            // Només hi pot haver una temporada actual.
            if ($data['is_current']) {
                Season::query()->where('is_current', true)
                    ->when($season !== null, fn ($q) => $q->whereKeyNot($season->id))
                    ->get()
                    ->each(fn (Season $other) => $other->update(['is_current' => false]));
            }

            if ($season === null) {
                return Season::query()->create($data);
            }

            $season->update($data);

            return $season;
        });
    }

    public function delete(Season $season): void
    {
        if ($season->matchdays()->exists()) {
            throw ValidationException::withMessages(['season' => __('messages.seasons.has_matchdays')]);
        }

        $season->delete();
    }
}
