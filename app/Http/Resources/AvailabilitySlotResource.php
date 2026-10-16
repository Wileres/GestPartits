<?php

namespace App\Http\Resources;

use App\Models\AvailabilitySlot;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AvailabilitySlot
 */
class AvailabilitySlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => $this->weekday,
            'specific_date' => $this->specific_date?->toDateString(),
            'label' => $this->label,
            'opens_at' => Time::hm($this->opens_at),
            'last_kickoff_at' => Time::hm($this->last_kickoff_at),
            'default_fill_order' => $this->default_fill_order,
            'is_active' => $this->is_active,
        ];
    }
}
