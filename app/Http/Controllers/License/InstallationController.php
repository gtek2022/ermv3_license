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

    /**
     * Mark all unreviewed suspicious events for this installation as reviewed.
     * Used by "Ignore All" button — admin telah inspect dan menganggapnya benign.
     */
    public function ignoreAllSuspicious(string $hash): RedirectResponse
    {
        $installation = $this->findOrFail($hash);

        $count = LicenseLogsSuspicious::where('installation_id', $installation->id)
            ->where('is_reviewed', false)
            ->update([
                'is_reviewed' => true,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        // Also ignore events tied to this license_company but missing installation_id
        // (e.g. invalid_key attempts where fingerprint didn't match any install)
        if ($installation->license_company_id) {
            $count += LicenseLogsSuspicious::where('license_company_id', $installation->license_company_id)
                ->whereNull('installation_id')
                ->where('is_reviewed', false)
                ->update([
                    'is_reviewed' => true,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
        }

        return back()->with('success', "Ignored {$count} suspicious event(s).");
    }

    /**
     * Bulk-ignore ALL unreviewed suspicious events across all installations.
     * Triggered from the dashboard "Ignore All" button so admin can clear
     * orphan events (those with installation_id/license_company_id null,
     * e.g. invalid-key attempts from random scanners).
     */
    public function ignoreAllSuspiciousGlobal(): RedirectResponse
    {
        $count = LicenseLogsSuspicious::where('is_reviewed', false)
            ->update([
                'is_reviewed' => true,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        return back()->with('success', "Ignored {$count} suspicious event(s).");
    }

    /**
     * Standalone listing page for ALL suspicious events (including orphan
     * ones not tied to any installation).
     */
    public function suspiciousIndex(Request $request): View
    {
        $query = LicenseLogsSuspicious::query();

        if ($request->filled('reviewed')) {
            $query->where('is_reviewed', $request->reviewed === '1');
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $events = $query->orderByDesc('occurred_at')->paginate(50);

        return view('license.installations.suspicious-index', compact('events'));
    }

    private function findOrFail(string $hash): LicenseInstallation
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return LicenseInstallation::findOrFail($ids[0]);
    }
}
