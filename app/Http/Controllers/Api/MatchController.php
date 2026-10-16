<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Matches\MatchRequest;
use App\Http\Requests\Matches\QuickEntryRequest;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Services\HomeAwaySuggester;
use App\Services\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MatchController extends Controller
{
    public function __construct(private readonly MatchService $matches) {}

    public function index(Matchday $matchday): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GameMatch::class);

        $matches = $matchday->matches()
            ->with(['team.category', 'fieldArea'])
            ->join('teams', 'teams.id', '=', 'matches.team_id')
            ->orderBy('teams.sort_order')
            ->orderBy('matches.id')
            ->select('matches.*')
            ->get();

        return MatchResource::collection($matches);
    }

    public function store(MatchRequest $request, Matchday $matchday): MatchResource
    {
        return new MatchResource($this->matches->create($matchday, $request->validated()));
    }

    public function storeMany(QuickEntryRequest $request, Matchday $matchday): AnonymousResourceCollection
    {
        return MatchResource::collection($this->matches->createMany($matchday, $request->validated('rows')));
    }

    public function show(GameMatch $match): MatchResource
    {
        $this->authorize('view', $match);

        return new MatchResource($match->load(['team.category', 'fieldArea']));
    }

    public function update(MatchRequest $request, GameMatch $match): MatchResource
    {
        return new MatchResource($this->matches->update($match, $request->validated()));
    }

    public function destroy(GameMatch $match): Response
    {
        $this->authorize('delete', $match);

        $this->matches->delete($match);

        return response()->noContent();
    }

    public function homeAwaySuggestions(Matchday $matchday, HomeAwaySuggester $suggester): JsonResponse
    {
        $this->authorize('viewAny', GameMatch::class);

        return response()->json(['data' => $suggester->forMatchday($matchday)]);
    }
}
