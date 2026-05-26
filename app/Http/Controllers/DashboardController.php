<?php

namespace App\Http\Controllers;

use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use App\Models\LicenseLogsSuspicious;
use App\Models\MasterCompany;
use App\Models\MasterConfig;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_companies'      => MasterCompany::count(),
            'active_licenses'      => LicenseCompany::where('status', 'active')->count(),
            'expired_licenses'     => LicenseCompany::where('status', 'expired')->count(),
            'suspended_licenses'   => LicenseCompany::where('status', 'suspended')->count(),
            'total_installations'  => LicenseInstallation::count(),
            'active_installations' => LicenseInstallation::where('status', 'active')->count(),
            'unreviewed_suspicious'=> LicenseLogsSuspicious::where('is_reviewed', false)->count(),
        ];

        // Heartbeat interval (config-driven) so dashboard can compute countdown
        $heartbeatInterval = (int) MasterConfig::get('licensing.heartbeat_interval', 3600);

        // Recent heartbeats — last 8 installations by last_heartbeat_at, with
        // computed countdown to next expected heartbeat (last + interval).
        $recentHeartbeats = LicenseInstallation::whereNotNull('last_heartbeat_at')
            ->orderByDesc('last_heartbeat_at')
            ->limit(8)
            ->get()
            ->map(function ($inst) use ($heartbeatInterval) {
                $nextExpected = $inst->last_heartbeat_at?->copy()->addSeconds($heartbeatInterval);
                $secondsUntilNext = $nextExpected
                    ? (int) now()->diffInSeconds($nextExpected, false)
                    : null;

                // Stale = lewat interval + 50% buffer (e.g. 1.5 jam untuk interval 1 jam)
                $isStale = $secondsUntilNext !== null
                    && $secondsUntilNext < -((int) ($heartbeatInterval * 0.5));

                $inst->next_expected_at  = $nextExpected;
                $inst->seconds_until_next = $secondsUntilNext;
                $inst->is_stale           = $isStale;

                return $inst;
            });

        // Unreviewed suspicious events
        $suspiciousEvents = LicenseLogsSuspicious::where('is_reviewed', false)
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get();

        // Expiring soon (within 30 days)
        $expiringSoon = LicenseCompany::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->orderBy('expires_at')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'stats',
            'recentHeartbeats',
            'suspiciousEvents',
            'expiringSoon',
            'heartbeatInterval'
        ));
    }
}
