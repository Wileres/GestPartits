<?php

namespace App\Http\Resources;

use App\Models\SchedulingRule;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchedulingRule
 */
class SchedulingRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'category_id' => $this->category_id,
            'game_format' => $this->game_format?->value,
            'scope_label' => $this->scopeLabel(),
            'weekday' => $this->weekday,
            'preferred_time' => Time::hm($this->preferred_time),
            'not_before' => Time::hm($this->not_before),
            'not_after' => Time::hm($this->not_after),
            'preferred_field_area_id' => $this->preferred_field_area_id,
            'priority' => $this->priority,
            'strength' => $this->strength->value,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
        ];
    }

    private function scopeLabel(): string
    {
        if ($this->team_id !== null) {
            return $this->relationLoaded('team') && $this->team !== null ? $this->team->name : '';
        }

        if ($this->category_id !== null) {
            return $this->relationLoaded('category') && $this->category !== null ? 'Categoria '.$this->category->name : '';
        }

        return $this->game_format?->label() ?? '';
    }
}
