<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\FieldBlockRequest;
use App\Http\Resources\FieldBlockResource;
use App\Models\FieldBlock;
use App\Support\ClubSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FieldBlockController extends Controller
{
    public function __construct(private readonly ClubSettings $settings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FieldBlock::class);

        // Per defecte, els bloquejos que encara no han acabat (dates locals del club).
        $from = $request->date('from')?->format('Y-m-d 00:00:00') ?? now(config('gestpartits.timezone'))->format('Y-m-d 00:00:00');

        return FieldBlockResource::collection(
            FieldBlock::query()
                ->with('fieldArea')
                ->where('venue_id', $this->settings->homeVenue()->id)
                ->where('ends_at', '>=', $from)
                ->orderBy('starts_at')
                ->get(),
        );
    }

    public function store(FieldBlockRequest $request): FieldBlockResource
    {
        $block = FieldBlock::query()->create([
            ...$this->normalize($request->validated()),
            'venue_id' => $this->settings->homeVenue()->id,
        ]);

        return new FieldBlockResource($block->load('fieldArea'));
    }

    public function update(FieldBlockRequest $request, FieldBlock $fieldBlock): FieldBlockResource
    {
        $fieldBlock->update($this->normalize($request->validated()));

        return new FieldBlockResource($fieldBlock->load('fieldArea'));
    }

    public function destroy(FieldBlock $fieldBlock): Response
    {
        $this->authorize('delete', $fieldBlock);

        $fieldBlock->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['starts_at'] = str_replace('T', ' ', $data['starts_at']).':00';
        $data['ends_at'] = str_replace('T', ' ', $data['ends_at']).':00';

        return $data;
    }
}
