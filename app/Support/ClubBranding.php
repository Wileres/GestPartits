<?php

namespace App\Support;

use App\Domain\Imaging\Branding;
use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Identitat visual configurada (nom, colors i escut del club).
 */
class ClubBranding
{
    public function __construct(private readonly ClubSettings $settings) {}

    /**
     * @return array{primary_color: string, primary_dark_color: string, secondary_color: string, logo_path: string|null}
     */
    public function values(): array
    {
        $value = (array) ApplicationSetting::get('branding', []);

        return [
            'primary_color' => (string) ($value['primary_color'] ?? '#0B4EA2'),
            'primary_dark_color' => (string) ($value['primary_dark_color'] ?? '#082F66'),
            'secondary_color' => (string) ($value['secondary_color'] ?? '#FFFFFF'),
            'logo_path' => $value['logo_path'] ?? null,
        ];
    }

    public function forImages(): Branding
    {
        $values = $this->values();
        $logo = $values['logo_path'] !== null && Storage::disk('local')->exists($values['logo_path'])
            ? Storage::disk('local')->get($values['logo_path'])
            : null;

        return new Branding($this->settings->clubName(), $values['primary_color'], $values['primary_dark_color'], $logo);
    }
}
