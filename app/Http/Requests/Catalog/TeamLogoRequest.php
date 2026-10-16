<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Escut d'un equip: només PNG, JPEG o WebP (el tipus es comprova pel contingut, no pel nom).
 * Els SVG no s'accepten perquè poden contenir scripts.
 */
class TeamLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('team'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('gestpartits.uploads.logo_max_kilobytes');
        $maxDim = (int) config('gestpartits.uploads.logo_max_dimension');

        return [
            'logo' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('gestpartits.uploads.logo_mimes')),
                'extensions:png,jpg,jpeg,webp',
                "max:{$maxKb}",
                "dimensions:max_width={$maxDim},max_height={$maxDim}",
            ],
        ];
    }

    public function attributes(): array
    {
        return ['logo' => 'escut'];
    }
}
