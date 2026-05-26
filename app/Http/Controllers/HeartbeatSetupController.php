<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

/**
 * Setup wizard for Gemilang admin to verify and configure cron / heartbeat
 * scheduling, and to provide one-click copy snippets for the client side.
 *
 * The page detects:
 *   - Whether Laravel scheduler is running on the gemilang server itself
 *     (used by `licensing:check-expirations` etc.)
 *   - Generates the exact crontab line for client servers (ERMv3)
 */
class HeartbeatSetupController extends Controller
{
    public function show(): View
    {
        $appPath = base_path();
        $phpBin  = PHP_BINARY;

        // The cron line client (ERMv3) servers should add
        $clientCronLine = "* * * * * cd <APP_PATH> && {$phpBin} artisan schedule:run >> /dev/null 2>&1";
        $genericCronLine = '* * * * * cd /www/wwwroot/erm.client.com && php artisan schedule:run >> /dev/null 2>&1';

        // Local checks (gemilang server)
        $checks = [
            'php_cli' => [
                'label' => 'PHP CLI tersedia',
                'value' => trim(shell_exec(escapeshellcmd($phpBin) . ' --version 2>&1') ?: ''),
                'ok'    => is_executable($phpBin),
            ],
            'app_path' => [
                'label' => 'Path aplikasi',
                'value' => $appPath,
                'ok'    => is_dir($appPath),
            ],
            'crontab_cmd' => [
                'label' => 'Command crontab',
                'value' => trim(shell_exec('which crontab 2>&1') ?: 'tidak terdeteksi'),
                'ok'    => ! empty(trim(shell_exec('which crontab 2>&1') ?: '')),
            ],
            'storage_writable' => [
                'label' => 'storage/ writable',
                'value' => is_writable(storage_path()) ? storage_path() : '✗ tidak writable',
                'ok'    => is_writable(storage_path()),
            ],
        ];

        // Laravel scheduler list
        $scheduledCommands = [];
        try {
            $output = Artisan::call('schedule:list');
            $scheduledCommands = explode("\n", trim(Artisan::output()));
        } catch (\Throwable $e) {
            $scheduledCommands = ['Error: ' . $e->getMessage()];
        }

        // Last cron run hint — file written by a tiny watch task
        $lastRunFile = storage_path('app/.cron-last-run');
        $lastRun = null;
        if (is_file($lastRunFile)) {
            $lastRun = \Carbon\Carbon::createFromTimestamp(filemtime($lastRunFile));
        }

        return view('system.heartbeat-setup', [
            'checks'             => $checks,
            'phpBin'             => $phpBin,
            'appPath'            => $appPath,
            'clientCronLine'     => $clientCronLine,
            'genericCronLine'    => $genericCronLine,
            'scheduledCommands'  => $scheduledCommands,
            'lastRun'            => $lastRun,
            'serverCronLine'     => "* * * * * cd {$appPath} && {$phpBin} artisan schedule:run >> /dev/null 2>&1",
        ]);
    }

    /**
     * Run a quick scheduler test — invoke the test marker command.
     */
    public function test(): \Illuminate\Http\JsonResponse
    {
        try {
            // Touch the last-run file so we have a concrete signal scheduler ran
            $file = storage_path('app/.cron-last-run');
            file_put_contents($file, now()->toIso8601String());

            // Try to run schedule:run manually
            Artisan::call('schedule:run');
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Scheduler berjalan ✓',
                'output'  => $output,
                'last_run' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduler error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
