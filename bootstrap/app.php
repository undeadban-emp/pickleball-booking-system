<?php

use App\Http\Middleware\EnsureCourtSlotsAreFresh;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\VerifyAppToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'app.token' => VerifyAppToken::class,
        ]);

        // Trust Cloudflare's edge as a reverse proxy so $request->ip() (and
        // therefore every per-IP rate limiter below) resolves to the real
        // visitor, not Cloudflare's proxy IP - without this, everyone behind
        // Cloudflare would appear to share one IP and rate limiting would be
        // meaningless. See config/cloudflare.php for the trusted IP ranges.
        // Loaded via a direct require (not the config() helper) since this
        // closure runs before the config service is bound in the container.
        $cloudflareIps = require __DIR__.'/../config/cloudflare.php';

        $middleware->trustProxies(
            at: array_merge(
                $cloudflareIps['ipv4'] ?? [],
                $cloudflareIps['ipv6'] ?? [],
            ),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Generous baseline against raw flooding of any page not covered by
        // a more specific limiter below (see RateLimiter::for('global', ...)
        // in AppServiceProvider).
        $middleware->web(append: ['throttle:global', EnsureCourtSlotsAreFresh::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
