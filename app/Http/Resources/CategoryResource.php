<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'game_format' => $this->game_format->value,
            'match_duration_min' => $this->match_duration_min,
            'post_min' => $this->post_min,
            'min_gap_min' => $this->min_gap_min,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'teams_count' => $this->whenCounted('teams'),
        ];
    }
}
