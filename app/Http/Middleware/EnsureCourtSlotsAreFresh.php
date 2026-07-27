<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-cron fallback for the rolling slot-generation window. `courts:generate-slots`
 * is scheduled ->daily() in routes/console.php, but Laravel's scheduler only
 * fires when something at the OS level calls `schedule:run` on a timer - on
 * shared hosting (Hostinger hPanel cron, etc.) that's easy to leave
 * unconfigured or misconfigured, and the rolling window silently stops
 * advancing when it does. This piggybacks the same command onto ordinary web
 * traffic instead: the first request of a new day runs it once, so the site
 * self-heals as long as it gets at least one visit per day. Complements the
 * real cron job rather than replacing it - the command is idempotent
 * (CourtSlot::firstOrCreate), so redundant runs from both are harmless.
 */
class EnsureCourtSlotsAreFresh
{
    private const CACHE_KEY = 'courts_slots_generated_on';

    public function handle(Request $request, Closure $next): Response
    {
        $today = today()->toDateString();

        if (Cache::get(self::CACHE_KEY) !== $today) {
            Cache::lock('courts_slots_generation', 10)->get(function () use ($today) {
                if (Cache::get(self::CACHE_KEY) !== $today) {
                    Artisan::call('courts:generate-slots');
                    Cache::put(self::CACHE_KEY, $today, now()->addDay());
                }
            });
        }

        return $next($request);
    }
}
