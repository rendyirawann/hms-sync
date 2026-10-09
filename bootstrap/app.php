<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'forbid-banned-user' => \Cog\Laravel\Ban\Http\Middleware\ForbidBannedUser::class,
            'hotel' => \App\Http\Middleware\ResolveHotelContext::class,
        ]);

        // Signed-in users who open a guest page (login, register) go to their own home:
        // guests to "Reservasi Saya", staff to the dashboard.
        $middleware->redirectUsersTo(fn ($request) => $request->user()?->homeUrl() ?? '/');

        // Tenant isolation: the hotel context must be set BEFORE route-model
        // binding, otherwise {booking} / {room} would resolve another hotel's row.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\ResolveHotelContext::class,
        );

        // Runs on every request, including assets served through PHP:
        // header hardening first, then the URL-signature guard.
        $middleware->prepend([
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\BlockSuspiciousRequests::class,
        ]);

        $middleware->web(append: [
            // Invalidates other sessions when the password changes and powers
            // "logout other devices".
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\TrackOnlineUsers::class,
            'throttle:web',
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\SanitizeInput::class,
        ]);

        // CSRF applies to every stateful route. Add a path here only for an
        // endpoint that authenticates by signature instead (e.g. a webhook).
        $middleware->validateCsrfTokens(except: ['payment/tripay/callback']);

        // Only trust a reverse proxy when one is actually configured —
        // trusting "*" without a proxy would let a client spoof its own IP
        // through X-Forwarded-For and defeat the rate limiter.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never leak stack traces or SQL through an XHR response.
        $exceptions->dontReport([]);
    })->create();
