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
        //
        // AttachHeartbeatPolicy adds the heartbeat cadence to a successful
        // /heartbeat response. It lives here rather than on the route because the
        // route belongs to the package - see the class docblock. It is additive
        // and self-silencing, so it cannot turn a good heartbeat into a bad one.
        $middleware->api(append: [
            \App\Http\Middleware\RecordSuspiciousLicensingEvent::class,
            \App\Http\Middleware\AttachHeartbeatPolicy::class,
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
        // Mark feature activations whose validity period has ended. Housekeeping only - clients lock
        // on the deadline itself, read from expires_at, so this keeps the status column honest for
        // reporting rather than being what enforces anything.
        //
        // Every minute rather than hourly since terms can now be minutes long. An hourly sweep would
        // leave a fifteen-minute trial showing as 'active' for most of its life again after it had
        // already stopped working, which is the exact confusion this command exists to avoid. The
        // command only touches rows whose deadline has already passed, so a run with nothing to do
        // costs one indexed query.
        $schedule->command('license:feature-expire')->everyMinute()->withoutOverlapping();
        // Trim the heartbeat log. It is the one table here that grows without bound - one row per
        // install per heartbeat, forever - and nothing removed any of them until this.
        $schedule->command('license:prune-logs')->dailyAt('03:20')->withoutOverlapping();
    })
    ->create();
