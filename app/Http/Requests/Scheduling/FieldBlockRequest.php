<?php

namespace App\Http\Requests\Scheduling;

use App\Models\FieldBlock;
use App\Support\ClubSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FieldBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $block = $this->route('field_block');

        return $block instanceof FieldBlock
            ? $this->user()->can('update', $block)
            : $this->user()->can('create', FieldBlock::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Buit = tot el camp.
            'field_area_id' => ['nullable', 'integer', Rule::exists('field_areas', 'id')->where('venue_id', app(ClubSettings::class)->homeVenue()->id)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['field_area_id' => 'camp', 'starts_at' => 'inici', 'ends_at' => 'final', 'reason' => 'motiu'];
    }
}
