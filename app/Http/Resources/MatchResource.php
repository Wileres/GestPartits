<?php

namespace App\Http\Resources;

use App\Models\GameMatch;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameMatch
 */
class MatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kickoff = Time::hm($this->kickoff_time);
        $kickoffMinutes = $kickoff === null ? null : Time::toMinutes($kickoff);

        return [
            'id' => $this->id,
            'matchday_id' => $this->matchday_id,
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'color' => $this->team->color,
                'is_active' => $this->team->is_active && ! $this->team->trashed(),
                'category' => $this->team->relationLoaded('category') ? [
                    'id' => $this->team->category->id,
                    'name' => $this->team->category->name,
                ] : null,
            ]),
            'round_label' => $this->round_label,
            'opponent' => $this->opponent,
            'home_away' => $this->home_away?->value,
            'status' => $this->status->value,
            'game_format' => $this->game_format->value,
            'match_date' => $this->match_date?->toDateString(),
            'weekday' => $this->match_date?->isoWeekday(),
            'kickoff_time' => $kickoff,
            'call_time' => Time::hm($this->call_time),
            'duration_min' => $this->duration_min,
            'post_min' => $this->post_min,
            // Final del partit i final de l'ocupació del camp (partit + marge posterior).
            'end_time' => $kickoffMinutes === null ? null : Time::fromMinutes($kickoffMinutes + $this->duration_min),
            'occupation_end_time' => $kickoffMinutes === null ? null : Time::fromMinutes($kickoffMinutes + $this->duration_min + $this->post_min),
            'field_area' => $this->whenLoaded('fieldArea', fn () => $this->fieldArea === null ? null : new FieldAreaResource($this->fieldArea)),
            'field_area_id' => $this->field_area_id,
            'venue_name' => $this->venue_name,
            'address' => $this->address,
            'municipality' => $this->municipality,
            'maps_url' => $this->maps_url,
            'is_locked' => $this->is_locked,
            'placement_source' => $this->placement_source,
            'rule_override_reason' => $this->rule_override_reason,
            'notes' => $this->notes,
            'whatsapp_extra' => $this->whatsapp_extra,
            'show_in_image' => $this->show_in_image,
            'score_team' => $this->score_team,
            'score_opponent' => $this->score_opponent,
            'has_result' => $this->hasResult(),
            'lock_version' => $this->lock_version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
