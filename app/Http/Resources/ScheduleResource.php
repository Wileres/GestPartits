<?php

namespace App\Http\Resources;

use App\Models\Schedule;
use App\Models\ScheduleVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Programació d'una jornada: estat, partits, conflictes, versions i les dades del motor
 * («input») perquè el calendari pugui validar els moviments al navegador.
 *
 * @property array<string, mixed> $resource
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Schedule $schedule */
        $schedule = $this->resource['schedule'];

        return [
            'id' => $schedule->id,
            'status' => $schedule->status->value,
            'status_label' => $schedule->status->label(),
            'version' => $schedule->lock_version,
            'confirmed_at' => $schedule->confirmed_at?->toIso8601String(),
            'confirmed_by' => $schedule->confirmer?->name,
            'has_unsaved_changes' => $this->resource['has_unsaved_changes'],
            'can_confirm' => $this->resource['blocking_conflicts'] === [],
            'matchday' => new MatchdayResource($schedule->matchday),
            'matches' => MatchResource::collection($this->resource['matches']),
            'conflicts' => $this->resource['conflicts'],
            'blocking_conflicts' => $this->resource['blocking_conflicts'],
            'input' => $this->resource['input']->toArray(),
            'versions' => $this->resource['versions']->map(fn (ScheduleVersion $v) => [
                'id' => $v->id,
                'number' => $v->number,
                'kind' => $v->kind->value,
                'kind_label' => $v->kind->label(),
                'summary' => $v->summary,
                'created_by' => $v->creator?->name,
                'created_at' => $v->created_at?->toIso8601String(),
                'is_current' => $v->id === $schedule->current_version_id,
            ])->values(),
            'proposal' => $this->resource['proposal'] ?? null,
        ];
    }
}
