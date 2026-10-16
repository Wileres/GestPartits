<?php

namespace App\Http\Resources;

use App\Models\FieldBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FieldBlock
 */
class FieldBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_area_id' => $this->field_area_id,
            'field_area' => $this->whenLoaded('fieldArea', fn () => $this->fieldArea === null ? null : new FieldAreaResource($this->fieldArea)),
            // Hora local del club, sense zona: «2026-10-17T09:00».
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->format('Y-m-d\TH:i'),
            'reason' => $this->reason,
        ];
    }
}
