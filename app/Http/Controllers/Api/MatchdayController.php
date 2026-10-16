<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\MatchdayRequest;
use App\Http\Resources\MatchdayResource;
use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MatchdayController extends Controller
{
    public function index(Season $season): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Matchday::class);

        return MatchdayResource::collection($season->matchdays()->withCount('matches')->get());
    }

    public function store(MatchdayRequest $request, Season $season): MatchdayResource
    {
        $matchday = $season->matchdays()->create($request->validated());

        return new MatchdayResource($matchday->loadCount('matches'));
    }

    public function show(Matchday $matchday): MatchdayResource
    {
        $this->authorize('view', $matchday);

        return new MatchdayResource($matchday->loadCount('matches'));
    }

    public function update(MatchdayRequest $request, Matchday $matchday): MatchdayResource
    {
        $matchday->update($request->validated());

        return new MatchdayResource($matchday->loadCount('matches'));
    }

    public function destroy(Matchday $matchday): Response
    {
        $this->authorize('delete', $matchday);

        if ($matchday->matches()->exists()) {
            throw ValidationException::withMessages(['matchday' => __('messages.matchdays.has_matches')]);
        }

        $matchday->delete();

        return response()->noContent();
    }
}
