<?php

namespace App\Http\Controllers;

use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use App\Models\LicenseLogsHeartbeat;
use App\Models\LicenseLogsSuspicious;
use App\Models\MasterCompany;
use App\Models\MasterConfig;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalLicenses    = LicenseCompany::count();
        $activeLicenses   = LicenseCompany::where('status', 'active')->count();
        $expiredLicenses  = LicenseCompany::where('status', 'expired')->count();
        $suspendedLicenses = LicenseCompany::where('status', 'suspended')->count();

        // Licenses expiring in next 30 days (alert metric)
        $expiringIn30 = LicenseCompany::where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->count();

        $stats = [
            'total_companies'       => MasterCompany::count(),
            'total_licenses'        => $totalLicenses,
            'active_licenses'       => $activeLicenses,
            'expired_licenses'      => $expiredLicenses,
            'suspended_licenses'    => $suspendedLicenses,
            'expiring_in_30'        => $expiringIn30,
            'total_installations'   => LicenseInstallation::count(),
            'active_installations'  => LicenseInstallation::where('status', 'active')->count(),
            'unreviewed_suspicious' => LicenseLogsSuspicious::where('is_reviewed', false)->count(),
            'heartbeats_last_24h'   => LicenseLogsHeartbeat::where('heartbeat_at', '>=', now()->subDay())->count(),
        ];

        // Heartbeat interval (config-driven) so dashboard can compute countdown
        $heartbeatInterval = (int) MasterConfig::get('licensing.heartbeat_interval', 3600);

        // Recent heartbeats with countdown to next expected ping
        $recentHeartbeats = LicenseInstallation::whereNotNull('last_heartbeat_at')
            ->orderByDesc('last_heartbeat_at')
            ->limit(8)
            ->get()
            ->map(function ($inst) use ($heartbeatInterval) {
                $nextExpected     = $inst->last_heartbeat_at?->copy()->addSeconds($heartbeatInterval);
                $secondsUntilNext = $nextExpected ? (int) now()->diffInSeconds($nextExpected, false) : null;
                $isStale          = $secondsUntilNext !== null && $secondsUntilNext < -((int) ($heartbeatInterval * 0.5));

                $inst->next_expected_at   = $nextExpected;
                $inst->seconds_until_next = $secondsUntilNext;
                $inst->is_stale           = $isStale;

                return $inst;
            });

        // Unreviewed suspicious events
        $suspiciousEvents = LicenseLogsSuspicious::where('is_reviewed', false)
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get();

        // Always show 5 nearest-to-expire licenses (closest deadline first), regardless of how far away.
        // This way the panel is never empty.
        $upcomingRenewals = LicenseCompany::with('company')
            ->whereNotNull('expires_at')
            ->whereIn('status', ['active', 'expired'])
            ->orderByRaw('CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END, expires_at ASC')
            ->limit(5)
            ->get()
            ->map(function ($lic) {
                $daysLeft = (int) round(now()->diffInDays($lic->expires_at, false));
                $lic->days_left = $daysLeft;

                if ($daysLeft < 0) {
                    $lic->urgency = 'expired';
                } elseif ($daysLeft <= 7) {
                    $lic->urgency = 'critical';
                } elseif ($daysLeft <= 30) {
                    $lic->urgency = 'warning';
                } elseif ($daysLeft <= 90) {
                    $lic->urgency = 'soon';
                } else {
                    $lic->urgency = 'safe';
                }

                return $lic;
            });

        // Recent activity feed: combine heartbeat logs + suspicious + recent installs
        $recentActivity = LicenseLogsHeartbeat::with('licenseCompany.company')
            ->orderByDesc('heartbeat_at')
            ->limit(6)
            ->get();

        return view('dashboard', compact(
            'stats',
            'recentHeartbeats',
            'suspiciousEvents',
            'upcomingRenewals',
            'recentActivity',
            'heartbeatInterval'
        ));
    }
}
