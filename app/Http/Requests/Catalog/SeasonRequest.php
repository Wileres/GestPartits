<?php

namespace App\Http\Requests\Catalog;

use App\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $season = $this->route('season');

        return $season instanceof Season
            ? $this->user()->can('update', $season)
            : $this->user()->can('create', Season::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:32', 'regex:/^\d{4}-\d{4}$/', Rule::unique('seasons', 'name')->ignore($this->route('season'))],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'is_current' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'starts_on' => 'data d\'inici', 'ends_on' => 'data de final', 'is_current' => 'temporada actual'];
    }

    public function messages(): array
    {
        return ['name.regex' => 'El nom ha de tenir el format 2026-2027.'];
    }
}
