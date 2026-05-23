<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Default API throttle used by the licensing routes group.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Limiter names referenced by the laravel-licensing package routes.
        RateLimiter::for('licensing-validate', fn (Request $r) => Limit::perMinute(
            (int) config('licensing.rate_limit.validate_per_minute', 60)
        )->by($r->ip()));

        RateLimiter::for('licensing-token', fn (Request $r) => Limit::perMinute(
            (int) config('licensing.rate_limit.token_per_minute', 20)
        )->by($r->ip()));

        RateLimiter::for('licensing-register', fn (Request $r) => Limit::perMinute(
            (int) config('licensing.rate_limit.register_per_minute', 30)
        )->by($r->ip()));

        // Share Hashids helper to all views so blade can call Hashids::encode()
        \Illuminate\Support\Facades\View::share('Hashids', app(\Vinkla\Hashids\Facades\Hashids::class));
    }
}
