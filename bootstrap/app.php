<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Append a terminate-only middleware that logs licensing API errors
        // (4xx with security-relevant codes) into license_logs_suspicious so
        // admins can review attack attempts via the dashboard.
        $middleware->api(append: [
            \App\Http\Middleware\RecordSuspiciousLicensingEvent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Tick file every minute → admin UI confirms cron is alive
        $schedule->command('cron:tick')->everyMinute()->withoutOverlapping();
        // Purge expired nonces every 10 minutes
        $schedule->call(fn () => \App\Models\LicenseNonce::purgeExpired())->everyTenMinutes();
        // Sync public key to master_configs daily (after key rotation)
        $schedule->command('license:sync-public-key')->daily();
    })
    ->create();
