<?php

namespace App\Http\Resources;

use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppTemplate
 */
class WhatsAppTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'applies_to' => $this->applies_to->value,
            'body' => $this->body,
            'line_body' => $this->line_body,
            'provider_template_name' => $this->provider_template_name,
            'is_active' => $this->is_active,
        ];
    }
}
