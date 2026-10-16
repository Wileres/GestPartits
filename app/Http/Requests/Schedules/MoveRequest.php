<?php

namespace App\Http\Requests\Schedules;

use App\Rules\TimeOnGrid;
use App\Rules\WithinOperatingHours;
use App\Support\ClubSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moviment d'un partit al calendari. Sense hora = tornar a «Partits sense programar».
 * El backend sempre torna a validar el moviment, encara que el navegador l'hagi considerat vàlid.
 */
class MoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('schedule'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $settings = app(ClubSettings::class);
        $hours = $settings->operatingHours();
        $matchday = $this->route('schedule')->loadMissing('matchday')->matchday;

        return [
            'version' => $this->routeIs('schedules.moves.validate') ? ['sometimes', 'integer'] : ['required', 'integer', 'min:0'],
            'match_id' => ['required', 'integer', Rule::exists('matches', 'id')->where('matchday_id', $matchday->id)->whereNull('deleted_at')],
            'match_date' => [
                'nullable', 'required_with:kickoff_time', 'date_format:Y-m-d',
                'after_or_equal:'.$matchday->starts_on->toDateString(), 'before_or_equal:'.$matchday->ends_on->toDateString(),
            ],
            'kickoff_time' => [
                'nullable', 'required_with:field_area_id', 'date_format:H:i',
                new TimeOnGrid($settings->kickoffStepMin()),
                new WithinOperatingHours($hours['first_kickoff'], $hours['last_kickoff']),
            ],
            'field_area_id' => ['nullable', 'required_with:kickoff_time', 'integer', Rule::exists('field_areas', 'id')->where('venue_id', $settings->homeVenue()->id)],
        ];
    }

    /**
     * @return array{match_date: string|null, kickoff_time: string|null, field_area_id: int|null}
     */
    public function target(): array
    {
        $kickoff = $this->input('kickoff_time');

        return [
            'match_date' => $this->input('match_date'),
            'kickoff_time' => $kickoff,
            'field_area_id' => $kickoff === null ? null : $this->integer('field_area_id'),
        ];
    }

    public function attributes(): array
    {
        return ['match_id' => 'partit', 'match_date' => 'data', 'kickoff_time' => 'hora del partit', 'field_area_id' => 'camp', 'version' => 'versió'];
    }

    public function messages(): array
    {
        return [
            'match_date.after_or_equal' => 'La data ha de ser dins de la jornada.',
            'match_date.before_or_equal' => 'La data ha de ser dins de la jornada.',
        ];
    }
}
