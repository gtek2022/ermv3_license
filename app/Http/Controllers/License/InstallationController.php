<?php

namespace App\Http\Controllers\License;

use App\Http\Controllers\Controller;
use App\Models\LicenseInstallation;
use App\Models\LicenseLogsAudit;
use App\Models\LicenseLogsSuspicious;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

class InstallationController extends Controller
{
    public function index(Request $request): View
    {
        $query = LicenseInstallation::with('licenseCompany.company');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('app_code')) {
            $query->where('app_code', $request->app_code);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('hostname', 'like', "%$s%")
                  ->orWhere('domain', 'like', "%$s%")
                  ->orWhere('ip_address', 'like', "%$s%")
                  ->orWhere('installation_uuid', 'like', "%$s%");
            });
        }

        $installations = $query->orderByDesc('last_heartbeat_at')->paginate(25);

        return view('license.installations.index', compact('installations'));
    }

    public function show(string $hash): View
    {
        $installation = $this->findOrFail($hash);
        $heartbeats   = $installation->heartbeatLogs()->orderByDesc('heartbeat_at')->limit(30)->get();
        $suspicious   = $installation->suspiciousEvents()->orderByDesc('occurred_at')->limit(10)->get();

        return view('license.installations.show', compact('installation', 'heartbeats', 'suspicious'));
    }

    public function revoke(Request $request, string $hash): RedirectResponse
    {
        $installation = $this->findOrFail($hash);

        $installation->update([
            'status'       => 'revoked',
            'revoked_at'   => now(),
            'revoke_reason'=> $request->input('reason', 'Revoked by admin'),
        ]);

        LicenseLogsAudit::record('revoked', 'license_installation', $installation->id, [
            'reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Installation revoked.');
    }

    public function blacklist(Request $request, string $hash): RedirectResponse
    {
        $installation = $this->findOrFail($hash);

        $installation->update([
            'status'        => 'blacklisted',
            'revoked_at'    => now(),
            'revoke_reason' => $request->input('reason', 'Blacklisted by admin'),
        ]);

        LicenseLogsAudit::record('blacklisted', 'license_installation', $installation->id, [
            'reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Installation blacklisted. Fingerprint will be rejected.');
    }

    public function reviewSuspicious(string $hash, int $eventId): RedirectResponse
    {
        $this->findOrFail($hash);

        LicenseLogsSuspicious::findOrFail($eventId)->update([
            'is_reviewed' => true,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Event marked as reviewed.');
    }

    private function findOrFail(string $hash): LicenseInstallation
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return LicenseInstallation::findOrFail($ids[0]);
    }
}
