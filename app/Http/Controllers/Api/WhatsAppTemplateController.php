<?php

namespace App\Http\Controllers\Api;

use App\Domain\Messaging\TemplateRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\WhatsAppTemplateRequest;
use App\Http\Resources\WhatsAppTemplateResource;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class WhatsAppTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsAppTemplate::class);

        return WhatsAppTemplateResource::collection(WhatsAppTemplate::query()->orderBy('kind')->orderBy('id')->get());
    }

    public function store(WhatsAppTemplateRequest $request): WhatsAppTemplateResource
    {
        $template = WhatsAppTemplate::query()->create([...$request->validated(), 'code' => Str::slug($request->string('name'), '_').'_'.Str::lower(Str::random(4))]);

        return new WhatsAppTemplateResource($template);
    }

    public function update(WhatsAppTemplateRequest $request, WhatsAppTemplate $whatsappTemplate): WhatsAppTemplateResource
    {
        $whatsappTemplate->update($request->validated());

        return new WhatsAppTemplateResource($whatsappTemplate);
    }

    public function destroy(WhatsAppTemplate $whatsappTemplate): Response
    {
        $this->authorize('delete', $whatsappTemplate);
        $whatsappTemplate->delete();

        return response()->noContent();
    }

    /**
     * Variables que es poden fer servir a cada tipus de plantilla (ajuda de l'editor).
     */
    public function variables(): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppTemplate::class);

        return response()->json(['data' => [
            'match' => TemplateRenderer::MATCH_VARIABLES,
            'weekend_summary' => TemplateRenderer::SUMMARY_VARIABLES,
            'line' => TemplateRenderer::LINE_VARIABLES,
        ]]);
    }
}
