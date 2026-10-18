<?php

namespace App\Http\Requests\Matches;

use App\Enums\HomeAway;
use App\Models\GameMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Full ràpid: crea d'una vegada els partits de la jornada (una fila per equip).
 */
class QuickEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GameMatch::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.team_id' => ['required', 'integer', 'distinct', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'rows.*.home_away' => ['required', Rule::enum(HomeAway::class)],
            'rows.*.opponent' => ['nullable', 'string', 'max:128'],
            'rows.*.round_label' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $teamIds = collect($this->input('rows'))->pluck('team_id');
                $existing = GameMatch::query()
                    ->where('matchday_id', $this->route('matchday')->id)
                    ->whereIn('team_id', $teamIds)
                    ->pluck('team_id')
                    ->all();

                foreach ($this->input('rows') as $index => $row) {
                    if (in_array($row['team_id'], $existing, true)) {
                        $validator->errors()->add("rows.{$index}.team_id", __('messages.validation.team_already_has_match'));
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'rows' => 'partits',
            'rows.*.team_id' => 'equip',
            'rows.*.home_away' => 'condició',
            'rows.*.opponent' => 'rival',
            'rows.*.round_label' => 'jornada de competició',
        ];
    }

    public function messages(): array
    {
        return ['rows.*.home_away.required' => 'Indica si el partit és a casa o a fora.'];
    }
}
