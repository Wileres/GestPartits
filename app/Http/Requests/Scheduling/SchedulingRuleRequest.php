<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\FieldAreaKind;
use App\Enums\GameFormat;
use App\Enums\RuleStrength;
use App\Models\Category;
use App\Models\FieldArea;
use App\Models\SchedulingRule;
use App\Models\Team;
use App\Rules\TimeOnGrid;
use App\Support\ClubSettings;
use App\Support\Time;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Regla de programació: àmbit (un equip, una categoria o una modalitat) i
 * preferència (dia + hora) i/o finestra (no abans de / no després de).
 */
class SchedulingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('scheduling_rule');

        return $rule instanceof SchedulingRule
            ? $this->user()->can('update', $rule)
            : $this->user()->can('create', SchedulingRule::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $settings = app(ClubSettings::class);
        $grid = new TimeOnGrid($settings->kickoffStepMin());

        return [
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'game_format' => ['nullable', Rule::enum(GameFormat::class)],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'preferred_time' => ['nullable', 'date_format:H:i', $grid],
            'not_before' => ['nullable', 'date_format:H:i', $grid],
            'not_after' => ['nullable', 'date_format:H:i', $grid],
            'preferred_field_area_id' => ['nullable', 'integer', Rule::exists('field_areas', 'id')->where('venue_id', $settings->homeVenue()->id)],
            'priority' => ['required', 'integer', 'between:-1000,1000'],
            'strength' => ['required', Rule::enum(RuleStrength::class)],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $scopes = array_filter([$this->input('team_id'), $this->input('category_id'), $this->input('game_format')], fn ($v) => $v !== null);
                if (count($scopes) === 0) {
                    $validator->errors()->add('team_id', __('messages.validation.rule_scope_required'));
                } elseif (count($scopes) > 1) {
                    $validator->errors()->add('team_id', __('messages.validation.rule_single_scope'));
                }

                $preferred = $this->input('preferred_time');
                $notBefore = $this->input('not_before');
                $notAfter = $this->input('not_after');

                if ($preferred === null && $notBefore === null && $notAfter === null) {
                    $validator->errors()->add('preferred_time', __('messages.validation.rule_content_required'));
                }

                if ($preferred !== null && $this->input('weekday') === null) {
                    $validator->errors()->add('weekday', __('messages.validation.rule_weekday_required'));
                }

                if ($notBefore !== null && $notAfter !== null && Time::toMinutes($notAfter) < Time::toMinutes($notBefore)) {
                    $validator->errors()->add('not_after', __('messages.validation.not_after_before_not_before'));
                }

                $areaId = $this->input('preferred_field_area_id');
                if ($areaId !== null && $preferred === null) {
                    $validator->errors()->add('preferred_field_area_id', __('messages.validation.rule_area_requires_time'));
                }

                if ($areaId !== null && $this->scopeFormat() === GameFormat::Football11 && FieldArea::query()->find($areaId)?->kind !== FieldAreaKind::Full) {
                    $validator->errors()->add('preferred_field_area_id', __('messages.validation.f11_full_field'));
                }
            },
        ];
    }

    private function scopeFormat(): ?GameFormat
    {
        if ($this->input('game_format') !== null) {
            return GameFormat::from($this->input('game_format'));
        }

        if ($this->input('team_id') !== null) {
            return Team::query()->with('category')->find($this->input('team_id'))?->effectiveGameFormat();
        }

        return $this->input('category_id') !== null ? Category::query()->find($this->input('category_id'))?->game_format : null;
    }

    public function attributes(): array
    {
        return [
            'team_id' => 'equip',
            'category_id' => 'categoria',
            'game_format' => 'modalitat',
            'weekday' => 'dia',
            'preferred_time' => 'hora preferent',
            'not_before' => 'no abans de',
            'not_after' => 'no després de',
            'preferred_field_area_id' => 'camp preferent',
            'priority' => 'prioritat',
            'strength' => 'tipus de regla',
            'valid_from' => 'vigent des de',
            'valid_to' => 'vigent fins a',
            'is_active' => 'activa',
            'notes' => 'descripció',
        ];
    }
}
