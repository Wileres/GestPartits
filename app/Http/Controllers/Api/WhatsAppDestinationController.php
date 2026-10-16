<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\WhatsAppDestinationRequest;
use App\Http\Resources\WhatsAppDestinationResource;
use App\Infrastructure\WhatsApp\WhatsAppProviderRegistry;
use App\Models\WhatsAppDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WhatsAppDestinationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsAppDestination::class);

        return WhatsAppDestinationResource::collection(WhatsAppDestination::query()->with('team')->orderBy('scope')->orderBy('team_id')->orderBy('id')->get());
    }

    public function store(WhatsAppDestinationRequest $request): WhatsAppDestinationResource
    {
        $data = $request->validated();
        $data['team_id'] = $data['scope'] === 'club' ? null : $data['team_id'];

        return new WhatsAppDestinationResource(WhatsAppDestination::query()->create($data)->load('team'));
    }

    public function update(WhatsAppDestinationRequest $request, WhatsAppDestination $whatsappDestination): WhatsAppDestinationResource
    {
        $data = $request->validated();
        $data['team_id'] = $data['scope'] === 'club' ? null : $data['team_id'];
        if (($data['external_id'] ?? null) === null) {
            unset($data['external_id']);
        }

        $whatsappDestination->update($data);

        return new WhatsAppDestinationResource($whatsappDestination->load('team'));
    }

    public function destroy(WhatsAppDestination $whatsappDestination): Response
    {
        $this->authorize('delete', $whatsappDestination);
        $whatsappDestination->delete();

        return response()->noContent();
    }

    /**
     * Proveïdors activats al servidor i què permet cadascun.
     */
    public function providers(WhatsAppProviderRegistry $registry): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppDestination::class);

        return response()->json(['data' => array_map(function (string $key) use ($registry) {
            $capabilities = $registry->for($key)->capabilities();

            return ['key' => $key, 'destination_types' => $capabilities->destinationTypes, 'manual' => $capabilities->manual, 'supports_media' => $capabilities->supportsMedia];
        }, $registry->available())]);
    }
}
