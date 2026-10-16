<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\TeamLogoRequest;
use App\Http\Requests\Catalog\TeamOrderRequest;
use App\Http\Requests\Catalog\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        return TeamResource::collection(
            Team::query()->with(['category', 'schedulingRules'])->orderBy('sort_order')->orderBy('name')->get(),
        );
    }

    public function store(TeamRequest $request): TeamResource
    {
        return new TeamResource($this->teams->create($request->validated()));
    }

    public function update(TeamRequest $request, Team $team): TeamResource
    {
        return new TeamResource($this->teams->update($team, $request->validated()));
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

    public function uploadLogo(TeamLogoRequest $request, Team $team): TeamResource
    {
        return new TeamResource($this->teams->storeLogo($team, $request->file('logo')));
    }

    public function deleteLogo(Team $team): TeamResource
    {
        $this->authorize('update', $team);

        return new TeamResource($this->teams->deleteLogo($team));
    }

    /**
     * L'escut es serveix des del disc privat, només a usuaris autenticats.
     */
    public function logo(Team $team): StreamedResponse
    {
        $this->authorize('view', $team);

        abort_if($team->logo_path === null || ! Storage::disk('local')->exists($team->logo_path), 404);

        return Storage::disk('local')->response($team->logo_path, $team->slug.'.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
