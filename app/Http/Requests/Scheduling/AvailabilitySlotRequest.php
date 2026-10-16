<?php

namespace App\Http\Requests\Scheduling;

use App\Models\AvailabilitySlot;
use App\Rules\TimeOnGrid;
use App\Support\ClubSettings;
use App\Support\Time;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Franja setmanal (weekday) o excepció d'una data concreta (specific_date).
 */
class AvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $slot = $this->route('availability_slot');

        return $slot instanceof AvailabilitySlot
            ? $this->user()->can('update', $slot)
            : $this->user()->can('create', AvailabilitySlot::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $grid = new TimeOnGrid(app(ClubSettings::class)->kickoffStepMin());

        return [
            'weekday' => ['nullable', 'required_without:specific_date', 'prohibits:specific_date', 'integer', 'between:1,7'],
            'specific_date' => ['nullable', 'required_without:weekday', 'date_format:Y-m-d'],
            'label' => ['required', 'string', 'max:64'],
            'opens_at' => ['required', 'date_format:H:i', $grid],
            'last_kickoff_at' => ['required', 'date_format:H:i', $grid],
            'default_fill_order' => ['nullable', 'integer', 'between:1,20'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (Time::toMinutes($this->input('last_kickoff_at')) < Time::toMinutes($this->input('opens_at'))) {
                    $validator->errors()->add('last_kickoff_at', __('messages.validation.last_kickoff_before_open'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'weekday' => 'dia de la setmana',
            'specific_date' => 'data concreta',
            'label' => 'nom',
            'opens_at' => 'primera hora d\'inici',
            'last_kickoff_at' => 'última hora d\'inici',
            'default_fill_order' => 'ordre d\'ompliment',
            'is_active' => 'activa',
        ];
    }

    public function messages(): array
    {
        return [
            'weekday.required_without' => 'Indica un dia de la setmana o una data concreta.',
            'specific_date.required_without' => 'Indica un dia de la setmana o una data concreta.',
            'weekday.prohibits' => 'Indica un dia de la setmana o una data concreta, però no tots dos.',
        ];
    }
}
