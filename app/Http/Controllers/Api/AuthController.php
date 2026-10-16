<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\MeResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuditLogger $audit): MeResource
    {
        $user = $request->authenticate($audit);

        // Evita la fixació de sessió.
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $audit->log('auth.login', $user, null, null, $user->id);

        return new MeResource($user->load('role'));
    }

    public function logout(Request $request, AuditLogger $audit): Response
    {
        $audit->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): MeResource|JsonResponse
    {
        return new MeResource($request->user()->load('role'));
    }
}
