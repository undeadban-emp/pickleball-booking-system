<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every /api route to the official Kitchen Line app builds. This is an
 * app-identity check, not a substitute for user authentication — the token
 * is shared by every install, so it only ever proves "this is our app", not
 * "this is a trusted user". Auth::sanctum + role middleware still guard
 * anything account-specific.
 */
class VerifyAppToken
{
    private const SECRET_KEY = 'pickleball-8f3k2m9x4q7w1p6n';

    public function handle(Request $request, Closure $next): Response
    {
        if (! hash_equals(self::SECRET_KEY, (string) $request->header('X-Jocos-Token'))) {
            abort(403, 'Invalid app token.');
        }

        return $next($request);
    }
}
