<?php

namespace App\Http\Requests\Schedules;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Operació sobre la programació: el client envia la versió que coneix (bloqueig optimista).
 */
class ScheduleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('schedule'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:0']];
    }

    public function attributes(): array
    {
        return ['version' => 'versió'];
    }

    public function version(): int
    {
        return $this->integer('version');
    }
}
