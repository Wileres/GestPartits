<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\AvailabilitySlotRequest;
use App\Http\Resources\AvailabilitySlotResource;
use App\Models\AvailabilitySlot;
use App\Support\ClubSettings;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AvailabilitySlotController extends Controller
{
    public function __construct(private readonly ClubSettings $settings) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AvailabilitySlot::class);

        return AvailabilitySlotResource::collection(
            AvailabilitySlot::query()
                ->where('venue_id', $this->settings->homeVenue()->id)
                ->orderByRaw('specific_date IS NOT NULL')
                ->orderBy('specific_date')
                ->orderBy('weekday')
                ->orderBy('opens_at')
                ->get(),
        );
    }

    public function store(AvailabilitySlotRequest $request): AvailabilitySlotResource
    {
        return new AvailabilitySlotResource(AvailabilitySlot::query()->create([
            ...$request->validated(),
            'venue_id' => $this->settings->homeVenue()->id,
        ]));
    }

    public function update(AvailabilitySlotRequest $request, AvailabilitySlot $availabilitySlot): AvailabilitySlotResource
    {
        $availabilitySlot->update([...$request->validated(), 'weekday' => $request->validated('weekday'), 'specific_date' => $request->validated('specific_date')]);

        return new AvailabilitySlotResource($availabilitySlot);
    }

    public function destroy(AvailabilitySlot $availabilitySlot): Response
    {
        $this->authorize('delete', $availabilitySlot);

        $availabilitySlot->delete();

        return response()->noContent();
    }
}
