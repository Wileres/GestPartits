<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FieldAreaResource;
use App\Support\ClubSettings;
use Illuminate\Http\JsonResponse;

/**
 * Dades de configuració que la SPA necessita per construir formularis i calendaris.
 */
class ReferenceDataController extends Controller
{
    public function __invoke(ClubSettings $settings): JsonResponse
    {
        $venue = $settings->homeVenue();

        return response()->json([
            'data' => [
                'club_name' => $settings->clubName(),
                'home_venue' => ['id' => $venue->id, 'name' => $venue->name],
                'field_areas' => FieldAreaResource::collection($venue->fieldAreas)->resolve(),
                'operating_hours' => $settings->operatingHours(),
                'kickoff_step_min' => $settings->kickoffStepMin(),
                'demo_durations_pending_review' => $settings->demoDurationsPendingReview(),
            ],
        ]);
    }
}
