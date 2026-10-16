<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\SchedulingRuleRequest;
use App\Http\Resources\SchedulingRuleResource;
use App\Models\SchedulingRule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SchedulingRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SchedulingRule::class);

        return SchedulingRuleResource::collection(
            SchedulingRule::query()->with(['team', 'category'])->orderByDesc('is_active')->orderByDesc('priority')->orderBy('id')->get(),
        );
    }

    public function store(SchedulingRuleRequest $request): SchedulingRuleResource
    {
        return new SchedulingRuleResource(SchedulingRule::query()->create($request->validated())->load(['team', 'category']));
    }

    public function update(SchedulingRuleRequest $request, SchedulingRule $schedulingRule): SchedulingRuleResource
    {
        $schedulingRule->update($request->validated());

        return new SchedulingRuleResource($schedulingRule->load(['team', 'category']));
    }

    public function destroy(SchedulingRule $schedulingRule): Response
    {
        $this->authorize('delete', $schedulingRule);

        $schedulingRule->delete();

        return response()->noContent();
    }
}
