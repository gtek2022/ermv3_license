<?php

namespace App\Http\Controllers;

use App\Services\Licensing\CronManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Setup wizard for Gemilang admin — multi-purpose monitor for cron health
 * and one-click installer (auto-detects aaPanel / cron.d / user crontab).
 */
class HeartbeatSetupController extends Controller
{
    public function show(CronManager $cron): View
    {
        return view('system.heartbeat-setup', [
            'capabilities'      => $cron->detectCapabilities(),
            'existingEntry'     => $cron->detectExistingEntry(),
            'tickFreshness'     => $cron->tickFreshness(),
            'cronLine'          => $cron->cronLine(),
            'scheduledCommands' => $this->getSchedules(),
            'clientCronExample' => '* * * * * cd /www/wwwroot/erm.client.com && php artisan schedule:run >> /dev/null 2>&1',
        ]);
    }

    public function status(CronManager $cron): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'tick'           => $cron->tickFreshness(),
                'existing_entry' => $cron->detectExistingEntry(),
                'now'            => now()->toIso8601String(),
            ],
        ]);
    }

    public function installCron(CronManager $cron): JsonResponse
    {
        $existing = $cron->detectExistingEntry();
        if ($existing) {
            return response()->json([
                'success' => true,
                'mode'    => $existing['mode'],
                'message' => 'Cron entry sudah terpasang.',
                'detail'  => $existing,
            ]);
        }

        $result = $cron->autoInstall();
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function uninstallCron(CronManager $cron): JsonResponse
    {
        $result = $cron->uninstall();
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function test(CronManager $cron): JsonResponse
    {
        try {
            Artisan::call('schedule:run');
            $output = Artisan::output();
            $cron->recordTick();

            return response()->json([
                'success'  => true,
                'message'  => 'Scheduler dijalankan ✓',
                'output'   => $output,
                'last_run' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduler error: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function getSchedules(): array
    {
        try {
            Artisan::call('schedule:list');
            return array_filter(explode("\n", trim(Artisan::output())));
        } catch (\Throwable $e) {
            return ['Error: ' . $e->getMessage()];
        }
    }
}
