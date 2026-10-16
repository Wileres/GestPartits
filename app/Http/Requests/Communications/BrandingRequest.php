<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class BrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'club_name' => ['required', 'string', 'max:128'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primary_dark_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['club_name' => 'nom del club', 'primary_color' => 'color principal', 'primary_dark_color' => 'color fosc'];
    }

    public function messages(): array
    {
        return ['primary_color.regex' => 'El color ha de tenir el format #RRGGBB.', 'primary_dark_color.regex' => 'El color ha de tenir el format #RRGGBB.'];
    }
}
