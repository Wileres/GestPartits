<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ClubBranding;
use App\Support\ClubSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nom i escut del club sense iniciar sessió (la pantalla d'accés els mostra). No exposa res més.
 */
class PublicBrandingController extends Controller
{
    public function __construct(private readonly ClubBranding $branding, private readonly ClubSettings $settings) {}

    public function show(): JsonResponse
    {
        $path = $this->branding->values()['logo_path'];

        return response()->json(['data' => [
            'club_name' => $this->settings->clubName(),
            'logo_url' => $path === null ? null : url('/api/public/logo').'?v='.substr(md5($path), 0, 8),
        ]]);
    }

    public function logo(): StreamedResponse
    {
        $path = $this->branding->values()['logo_path'];
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'escut.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
