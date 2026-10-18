<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    private function decoration(string $key): mixed
    {
        return array_key_exists($key, $this->resource->getAttributes()) ? $this->resource->getAttributes()[$key] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'game_format' => $this->category->game_format->value,
            ]),
            // Valors propis (null = hereta de la categoria) i valors efectius.
            'game_format' => $this->game_format?->value,
            'match_duration_min' => $this->match_duration_min,
            'post_min' => $this->post_min,
            'effective' => $this->whenLoaded('category', fn () => [
                'game_format' => $this->effectiveGameFormat()->value,
                'match_duration_min' => $this->effectiveDurationMin(),
                'post_min' => $this->effectivePostMin(),
            ]),
            'color' => $this->color,
            // Horari preferent a casa: el propi (editable) i, si no n'hi ha, l'heretat de la categoria o la modalitat.
            'preferred_schedule' => $this->decoration('preferred_schedule'),
            'inherited_preference' => $this->decoration('inherited_preference'),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'scheduling_rules' => SchedulingRuleResource::collection($this->whenLoaded('schedulingRules')),
        ];
    }
}
