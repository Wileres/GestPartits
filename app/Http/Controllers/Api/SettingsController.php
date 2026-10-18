<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\BrandingRequest;
use App\Http\Requests\Communications\ClubLogoRequest;
use App\Models\ApplicationSetting;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Support\ClubBranding;
use App\Support\ClubSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aparença: nom, colors i escut del club (imatges d'Instagram).
 */
class SettingsController extends Controller
{
    public function __construct(private readonly ClubBranding $branding, private readonly ClubSettings $settings) {}

    public function show(): JsonResponse
    {
        return $this->state();
    }

    public function update(BrandingRequest $request, AuditLogger $audit): JsonResponse
    {
        $old = ['club_name' => $this->settings->clubName(), ...$this->branding->values()];
        $values = $this->branding->values();

        ApplicationSetting::put('club_name', $request->string('club_name')->value(), $request->user()->id);
        ApplicationSetting::put('branding', [...$values, 'primary_color' => strtoupper($request->string('primary_color')), 'primary_dark_color' => strtoupper($request->string('primary_dark_color'))], $request->user()->id);

        $audit->log('settings.branding_updated', null, $old, $request->validated());

        return $this->state();
    }

    public function uploadLogo(ClubLogoRequest $request, ImageUploadService $uploads): JsonResponse
    {
        $values = $this->branding->values();
        $path = $uploads->storePng($request->file('logo'), 'logos/club');
        ApplicationSetting::put('branding', [...$values, 'logo_path' => $path], $request->user()->id);
        $uploads->delete($values['logo_path']);

        return $this->state();
    }

    public function deleteLogo(Request $request, ImageUploadService $uploads): JsonResponse
    {
        $this->authorize('manage-settings');
        $values = $this->branding->values();
        $uploads->delete($values['logo_path']);
        ApplicationSetting::put('branding', [...$values, 'logo_path' => null], $request->user()->id);

        return $this->state();
    }

    private function state(): JsonResponse
    {
        $values = $this->branding->values();

        return response()->json(['data' => [
            'club_name' => $this->settings->clubName(),
            'primary_color' => $values['primary_color'],
            'primary_dark_color' => $values['primary_dark_color'],
            'has_logo' => $values['logo_path'] !== null,
            'logo_url' => $values['logo_path'] === null ? null : url('/api/public/logo').'?v='.substr(md5($values['logo_path']), 0, 8),
        ]]);
    }
}
