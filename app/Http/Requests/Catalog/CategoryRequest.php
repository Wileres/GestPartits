<?php

namespace App\Http\Requests\Catalog;

use App\Enums\GameFormat;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('categories', 'name')->whereNull('deleted_at')->ignore($this->route('category'))],
            'game_format' => ['required', Rule::enum(GameFormat::class)],
            'match_duration_min' => ['required', 'integer', 'min:1', 'max:300'],
            'post_min' => ['required', 'integer', 'min:0', 'max:120'],
            'min_gap_min' => ['required', 'integer', 'min:0', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'game_format' => 'modalitat',
            'match_duration_min' => 'durada del partit',
            'post_min' => 'marge posterior',
            'min_gap_min' => 'interval mínim entre partits',
            'sort_order' => 'ordre',
            'is_active' => 'actiu',
        ];
    }
}
