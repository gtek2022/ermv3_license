<?php

namespace App\Http\Controllers;

use App\Models\LicenseInstallation;
use App\Models\LicenseLogsHeartbeat;
use App\Models\LicenseLogsSuspicious;
use App\Models\MasterConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Central Heartbeat Monitor — the server-side counterpart to the per-client
 * License Diagnostics page. Lets an admin see, across ALL licensed
 * installations, which clients are heartbeating, which are going stale, and
 * which have been silent long enough that their offline token has expired
 * (meaning that client has likely bounced back to its activation page).
 *
 * Eloquent-only per project DB rules — no raw queries.
 */
class HeartbeatMonitorController extends Controller
{
    public function index(): View
    {
        return view('heartbeat-monitor', ['report' => $this->report()]);
    }

    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->report()]);
    }

    // ── Report builder ──────────────────────────────────────────────────────

    protected function report(): array
    {
        $interval  = (int) MasterConfig::get('licensing.heartbeat_interval', 3600);
        $ttlDays   = (int) config('licensing.offline_token.ttl_days', 7);
        $staleSecs = max(120, (int) ($interval * 1.5));
        $ttlSecs   = max($staleSecs + 60, $ttlDays * 86400);
        $nowTs     = now()->getTimestamp();

        $installations = LicenseInstallation::with('licenseCompany.company')
            ->where('status', 'active')
            ->orderByDesc('last_heartbeat_at')
            ->get()
            ->map(function ($i) use ($nowTs, $interval, $staleSecs, $ttlSecs) {
                $last = $i->last_heartbeat_at;
                $age  = $last ? max(0, $nowTs - $last->getTimestamp()) : null;

                $health = $this->classify($age, $staleSecs, $ttlSecs);

                $nextInSecs = ($age !== null) ? ($interval - $age) : null;

                return [
                    'company'        => optional(optional($i->licenseCompany)->company)->name
                                        ?? optional($i->licenseCompany)->label
                                        ?? '—',
                    'app_code'       => $i->app_code,
                    'domain'         => $i->domain,
                    'ip_address'     => $i->ip_address,
                    'app_version'    => $i->app_version,
                    'fingerprint'    => $i->fingerprint ? substr($i->fingerprint, 0, 12) . '…' : null,
                    'last_heartbeat' => $last?->toIso8601String(),
                    'age_seconds'    => $age,
                    'days_since'     => $age !== null ? (int) floor($age / 86400) : null,
                    'next_in_secs'   => $nextInSecs,
                    'health'         => $health,
                    'violation'      => (int) ($i->violation_counter ?? 0),
                ];
            });

        // Summary tallies (computed in PHP from the mapped collection).
        $counts = [
            'total'   => $installations->count(),
            'online'  => $installations->where('health', 'online')->count(),
            'late'    => $installations->where('health', 'late')->count(),
            'expired' => $installations->where('health', 'expired')->count(),
            'never'   => $installations->where('health', 'never')->count(),
        ];

        // Heartbeat traffic in the last 24h (Eloquent count queries).
        $since   = now()->subDay();
        $hb24Ok  = LicenseLogsHeartbeat::where('heartbeat_at', '>=', $since)
            ->whereIn('status', ['success', 'verified'])->count();
        $hb24Bad = LicenseLogsHeartbeat::where('heartbeat_at', '>=', $since)
            ->whereNotIn('status', ['success', 'verified'])->count();

        // Installations that need attention: token likely expired (silent > ttl)
        // or carrying a failed/rejected recent heartbeat.
        $attention = $installations->whereIn('health', ['expired', 'late'])->values();

        // Recent heartbeat log feed (includes failures + reasons).
        $recent = LicenseLogsHeartbeat::with('licenseCompany.company')
            ->orderByDesc('heartbeat_at')
            ->limit(40)
            ->get()
            ->map(function ($h) {
                $ok = in_array($h->status, ['success', 'verified'], true);

                return [
                    'company'      => optional(optional($h->licenseCompany)->company)->name
                                      ?? optional($h->licenseCompany)->label
                                      ?? '—',
                    'app_code'     => $h->app_code,
                    'ip_address'   => $h->ip_address,
                    'app_version'  => $h->app_version,
                    'status'       => $h->status,
                    'ok'           => $ok,
                    'reason'       => $h->failure_reason,
                    'at'           => $h->heartbeat_at?->toIso8601String(),
                ];
            });

        return [
            'now'                 => now()->toIso8601String(),
            'interval_seconds'    => $interval,
            'ttl_days'            => $ttlDays,
            'counts'              => $counts,
            'heartbeats_24h_ok'   => $hb24Ok,
            'heartbeats_24h_bad'  => $hb24Bad,
            'unreviewed_suspicious' => LicenseLogsSuspicious::where('is_reviewed', false)->count(),
            'installations'       => $installations->values(),
            'attention'           => $attention,
            'recent'              => $recent,
        ];
    }

    /**
     * Classify an installation's heartbeat freshness.
     *   online  — pinged within 1.5× the interval
     *   late    — silent past that but within the token TTL (still licensed)
     *   expired — silent longer than the token TTL → client's offline token has
     *             likely expired and it has bounced to its activation page
     *   never   — never heartbeated
     */
    protected function classify(?int $age, int $staleSecs, int $ttlSecs): string
    {
        if ($age === null) {
            return 'never';
        }
        if ($age <= $staleSecs) {
            return 'online';
        }
        if ($age <= $ttlSecs) {
            return 'late';
        }

        return 'expired';
    }
}
