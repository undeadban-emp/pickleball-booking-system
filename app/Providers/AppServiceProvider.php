<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    /**
     * Named rate limiters applied via `throttle:<name>` middleware on
     * specific routes in routes/web.php and routes/api.php - see
     * bootstrap/app.php for the generous 'global' baseline applied to every
     * web route as a safety net, and the trustProxies() call that makes
     * `$request->ip()` resolve correctly once the site sits behind
     * Cloudflare (without it, every visitor would appear to share one IP).
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('global', fn ($request) => Limit::perMinute(300)->by($request->ip()));

        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Classic brute-force pattern: keyed by email+IP together, so one
        // attacker IP can't lock a real customer out of their own account by
        // spraying their email from elsewhere, but repeated guesses against
        // one account from one IP still get throttled.
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn ($request) => Limit::perHour(5)->by($request->ip()));

        // Guest/account booking creation - the direct "slot spam" surface.
        RateLimiter::for('booking-write', fn ($request) => Limit::perMinute(10)->by($request->ip()));

        // File-upload endpoints (GCash reference + proof of payment).
        RateLimiter::for('payment-reference', fn ($request) => Limit::perMinute(8)->by($request->ip()));

        RateLimiter::for('booking-write-light', fn ($request) => Limit::perMinute(10)->by($request->ip()));

        // Booking/order status polling - legit client already polls every
        // 15-20s per tab, this allows several concurrent tabs with headroom.
        RateLimiter::for('status-poll', fn ($request) => Limit::perMinute(20)->by($request->ip()));

        // Most DB-costly open endpoints (each triggers a stale-booking sweep
        // on every hit) - see Api/AvailabilityController and Api/CourtController.
        RateLimiter::for('availability-read', fn ($request) => Limit::perMinute(30)->by($request->ip()));
    }
}
