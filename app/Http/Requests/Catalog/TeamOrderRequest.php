<?php

namespace App\Http\Requests\Catalog;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Team::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'team_ids' => ['required', 'array', 'min:1', 'max:200'],
            'team_ids.*' => ['integer', 'distinct', Rule::exists('teams', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return ['team_ids' => 'ordre dels equips', 'team_ids.*' => 'equip'];
    }
}
