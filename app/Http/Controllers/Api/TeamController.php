<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\TeamOrderRequest;
use App\Http\Requests\Catalog\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        $teams = Team::query()->with(['category', 'schedulingRules'])->orderBy('sort_order')->orderBy('name')->get();
        $this->teams->decorate($teams);

        return TeamResource::collection($teams);
    }

    public function store(TeamRequest $request): TeamResource
    {
        return new TeamResource($this->decorated($this->teams->create($request->validated())));
    }

    public function update(TeamRequest $request, Team $team): TeamResource
    {
        return new TeamResource($this->decorated($this->teams->update($team, $request->validated())));
    }

    public function destroy(Team $team): Response
    {
        $this->authorize('delete', $team);

        $team->delete();

        return response()->noContent();
    }

    public function reorder(TeamOrderRequest $request): Response
    {
        $this->teams->reorder($request->validated('team_ids'));

        return response()->noContent();
    }

    private function decorated(Team $team): Team
    {
        $team->load('schedulingRules');
        $this->teams->decorate($team);

        return $team;
    }
}
