<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SeasonRequest;
use App\Http\Resources\SeasonResource;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SeasonController extends Controller
{
    public function __construct(private readonly SeasonService $seasons) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Season::class);

        return SeasonResource::collection(Season::query()->withCount('matchdays')->orderByDesc('starts_on')->get());
    }

    public function store(SeasonRequest $request): SeasonResource
    {
        return new SeasonResource($this->seasons->save(null, $request->validated())->loadCount('matchdays'));
    }

    public function update(SeasonRequest $request, Season $season): SeasonResource
    {
        return new SeasonResource($this->seasons->save($season, $request->validated())->loadCount('matchdays'));
    }

    public function destroy(Season $season): Response
    {
        $this->authorize('delete', $season);

        $this->seasons->delete($season);

        return response()->noContent();
    }
}
