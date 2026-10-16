<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\SendMessagesRequest;
use App\Http\Resources\WhatsAppMessageResource;
use App\Models\Schedule;
use App\Models\WhatsAppMessage;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Historial i esborranys de la jornada. */
    public function index(Schedule $schedule): AnonymousResourceCollection
    {
        $this->authorize('view', $schedule);

        return WhatsAppMessageResource::collection($this->notifications->messages($schedule));
    }

    /** Prepara (o actualitza) els esborranys. No envia res. */
    public function preview(Schedule $schedule): AnonymousResourceCollection
    {
        $this->authorize('update', $schedule);

        return WhatsAppMessageResource::collection($this->notifications->preview($schedule));
    }

    public function update(Request $request, WhatsAppMessage $whatsappMessage): WhatsAppMessageResource
    {
        $this->authorize('update', $whatsappMessage->loadMissing('schedule')->schedule);
        $data = $request->validate(['body' => ['required', 'string', 'max:4096']], [], ['body' => 'text del missatge']);

        return new WhatsAppMessageResource($this->notifications->updateDraft($whatsappMessage, $data['body'])->load(['destination.team', 'match.team', 'attachments.generatedImage']));
    }

    public function send(SendMessagesRequest $request, Schedule $schedule): JsonResponse
    {
        $result = $this->notifications->send($schedule, $request->validated('message_ids'));

        return response()->json(['data' => [...$result, 'messages' => WhatsAppMessageResource::collection($this->notifications->messages($schedule))]]);
    }

    public function retryFailed(Schedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);
        $queued = $this->notifications->retryFailed($schedule);

        return response()->json(['data' => ['queued' => $queued, 'messages' => WhatsAppMessageResource::collection($this->notifications->messages($schedule))]]);
    }

    public function markSent(WhatsAppMessage $whatsappMessage): WhatsAppMessageResource
    {
        $this->authorize('update', $whatsappMessage->loadMissing('schedule')->schedule);

        return new WhatsAppMessageResource($this->notifications->markSent($whatsappMessage)->load(['destination.team', 'match.team', 'attachments.generatedImage']));
    }

    public function discard(WhatsAppMessage $whatsappMessage): WhatsAppMessageResource
    {
        $this->authorize('update', $whatsappMessage->loadMissing('schedule')->schedule);

        return new WhatsAppMessageResource($this->notifications->discard($whatsappMessage)->load(['destination.team', 'match.team', 'attachments.generatedImage']));
    }
}
