<?php

namespace App\Http\Requests\Catalog;

use App\Enums\GameFormat;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:64', Rule::unique('teams', 'name')->whereNull('deleted_at')->ignore($this->route('team'))],
            // Buit = hereta de la categoria.
            'game_format' => ['nullable', Rule::enum(GameFormat::class)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'match_duration_min' => ['nullable', 'integer', 'min:1', 'max:300'],
            'post_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['required', 'boolean'],
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
        ];
    }

    public function messages(): array
    {
        return ['color.regex' => 'El color ha de tenir el format #RRGGBB.'];
    }
}
