<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mentre l'usuari tingui la contrasenya inicial, només pot canviar-la o tancar la sessió.
 */
class EnsurePasswordChanged
{
    /** Rutes permeses abans de canviar la contrasenya. */
    private const ALLOWED_ROUTES = ['auth.me', 'auth.logout', 'auth.password.change'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return response()->json([
                'message' => __('auth.password_change_required'),
                'code' => 'password_change_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
