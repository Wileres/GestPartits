<?php

namespace App\Http\Resources;

use App\Models\Matchday;
use App\Support\CatalanDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Matchday
 */
class MatchdayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'season_id' => $this->season_id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'label' => $this->label,
            'display_label' => $this->label ?? CatalanDate::matchdayLabel($this->starts_on, $this->ends_on),
            'matches_count' => $this->whenCounted('matches'),
        ];
    }
}
