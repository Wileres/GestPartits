<?php

namespace App\Http\Requests\Catalog;

use App\Enums\FieldAreaKind;
use App\Enums\GameFormat;
use App\Models\Category;
use App\Models\FieldArea;
use App\Models\Team;
use App\Rules\TimeOnGrid;
use App\Rules\WithinOperatingHours;
use App\Support\ClubSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            ? $this->user()->can('update', $team)
            : $this->user()->can('create', Team::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $settings = app(ClubSettings::class);
        $hours = $settings->operatingHours();

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:64', Rule::unique('teams', 'name')->whereNull('deleted_at')->ignore($this->route('team'))],
            // Buit = hereta de la categoria.
            'game_format' => ['nullable', Rule::enum(GameFormat::class)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'match_duration_min' => ['nullable', 'integer', 'min:1', 'max:300'],
            'post_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['required', 'boolean'],
            // Horari preferent a casa. Sense la clau no es toca; null l'esborra.
            'preferred_schedule' => ['sometimes', 'nullable', 'array'],
            'preferred_schedule.weekday' => ['required_with:preferred_schedule', 'integer', 'between:1,7'],
            'preferred_schedule.time' => [
                'required_with:preferred_schedule', 'date_format:H:i',
                new TimeOnGrid($settings->kickoffStepMin()),
                new WithinOperatingHours($hours['first_kickoff'], $hours['last_kickoff']),
            ],
            'preferred_schedule.field_area_id' => ['nullable', 'integer', Rule::exists('field_areas', 'id')->where('venue_id', $settings->homeVenue()->id)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $areaId = $this->input('preferred_schedule.field_area_id');

                if ($validator->errors()->isNotEmpty() || $areaId === null) {
                    return;
                }

                $format = $this->input('game_format') ?? Category::query()->find($this->input('category_id'))?->game_format->value;

                if ($format === GameFormat::Football11->value && FieldArea::query()->find($areaId)?->kind !== FieldAreaKind::Full) {
                    $validator->errors()->add('preferred_schedule.field_area_id', __('messages.validation.f11_full_field'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'categoria',
            'name' => 'nom',
            'game_format' => 'modalitat',
            'color' => 'color',
            'match_duration_min' => 'durada del partit',
            'post_min' => 'marge posterior',
            'is_active' => 'actiu',
            'preferred_schedule.weekday' => 'dia preferent',
            'preferred_schedule.time' => 'hora preferent',
            'preferred_schedule.field_area_id' => 'camp preferent',
        ];
    }

    public function messages(): array
    {
        return ['color.regex' => 'El color ha de tenir el format #RRGGBB.'];
    }
}
