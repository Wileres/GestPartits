<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Escut del club per a les imatges: PNG, JPEG o WebP (el tipus es comprova pel contingut).
 */
class ClubLogoRequest extends FormRequest
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
        $maxKb = (int) config('gestpartits.uploads.logo_max_kilobytes');
        $maxDim = (int) config('gestpartits.uploads.logo_max_dimension');

        return [
            'logo' => ['required', 'file', 'mimetypes:'.implode(',', config('gestpartits.uploads.logo_mimes')), 'extensions:png,jpg,jpeg,webp', "max:{$maxKb}", "dimensions:max_width={$maxDim},max_height={$maxDim}"],
        ];
    }

    public function attributes(): array
    {
        return ['logo' => 'escut'];
    }
}
