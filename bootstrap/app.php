<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // La SPA s'autentica amb la cookie de sessió (Sanctum stateful) i protecció CSRF.
        $middleware->statefulApi();
        // Darrere d'un proxy invers (p. ex. un balancejador amb HTTPS), indiqueu-ne les IP a TRUSTED_PROXIES.
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->throttleApi('api');
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Missatges d'error de l'API en català.
        $json = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $json($request)
            ? response()->json(['message' => __('auth.unauthenticated')], 401) : null);

        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e, Request $request) => $json($request)
            ? response()->json(['message' => __('messages.http.403')], 403) : null);

        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $request) => $json($request)
            ? response()->json(['message' => __('messages.http.404')], 404) : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $json($request)
            ? response()->json(['message' => __('messages.http.429')], 429, $e->getHeaders()) : null);

        $exceptions->render(fn (TokenMismatchException $e, Request $request) => $json($request)
            ? response()->json(['message' => __('messages.http.419')], 419) : null);

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($json) {
            if (! $json($request) || $e->getStatusCode() === 422) {
                return null;
            }

            // Els 409 de l'aplicació porten un missatge propi en català (p. ex. programació modificada).
            if ($e->getStatusCode() === 409 && $e instanceof Throwable && $e->getMessage() !== '') {
                return response()->json(['message' => $e->getMessage()], 409, $e->getHeaders());
            }

            $key = 'messages.http.'.$e->getStatusCode();

            return response()->json(['message' => Lang::has($key) ? __($key) : __('messages.http.default')], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();
