<?php

namespace App\Http\Controllers;

use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use App\Models\LicenseLogsSuspicious;
use App\Models\MasterCompany;
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

        // Recent heartbeats — last 10 installations by last_heartbeat_at
        $recentHeartbeats = LicenseInstallation::whereNotNull('last_heartbeat_at')
            ->orderByDesc('last_heartbeat_at')
            ->limit(8)
            ->get();

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
            'expiringSoon'
        ));
    }
}
