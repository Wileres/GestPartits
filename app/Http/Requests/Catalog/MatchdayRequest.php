<?php

namespace App\Http\Requests\Catalog;

use App\Models\Matchday;
use App\Models\Season;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MatchdayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $matchday = $this->route('matchday');

        return $matchday instanceof Matchday
            ? $this->user()->can('update', $matchday)
            : $this->user()->can('create', Matchday::class);
    }

    public function season(): Season
    {
        $matchday = $this->route('matchday');

        return $matchday instanceof Matchday ? $matchday->season : $this->route('season');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_on' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('matchdays', 'starts_on')->where('season_id', $this->season()->id)->ignore($this->route('matchday')),
            ],
            // Una jornada pot incloure un festiu: fins a 4 dies.
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'label' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = CarbonImmutable::parse($this->input('starts_on'));
                $end = CarbonImmutable::parse($this->input('ends_on'));
                $season = $this->season();

                if ($start->lt($season->starts_on) || $end->gt($season->ends_on)) {
                    $validator->errors()->add('starts_on', __('messages.validation.matchday_outside_season'));
                }

                if ($start->diffInDays($end) > 3) {
                    $validator->errors()->add('ends_on', 'Una jornada pot durar com a màxim 4 dies.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['starts_on' => 'primer dia', 'ends_on' => 'últim dia', 'label' => 'nom'];
    }

    public function messages(): array
    {
        return ['starts_on.unique' => 'Ja hi ha una jornada que comença aquest dia.'];
    }
}
