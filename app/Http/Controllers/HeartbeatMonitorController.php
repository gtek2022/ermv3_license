<?php

namespace App\Http\Controllers;

use App\Models\LicenseInstallation;
use App\Models\LicenseLogsHeartbeat;
use App\Models\LicenseLogsSuspicious;
use App\Models\MasterConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

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
    /**
     * Stable contract: every client app (ermv3, absensi, …) exposes its
     * self-diagnostics at this fixed path. Keeping it identical across clients
     * means gemilang never has to special-case per app.
     */
    public const CLIENT_DIAGNOSTICS_PATH = '/license/diagnostics';

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
                    'hash'           => Hashids::encode($i->id),
                    'uuid'           => $i->installation_uuid,
                    'client_url'     => $this->clientDiagnosticsUrl($i->domain),
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
        ];
    }

    /**
     * GET /heartbeat-monitor/logs
     * Server-side paginated heartbeat log feed (browse the full history), with
     * an OK/failed filter and free-text search. Separate from the live report()
     * poll so the table can page through everything without re-loading the
     * whole dashboard.
     */
    public function logs(\Illuminate\Http\Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));
        $filter  = (string) $request->input('status', 'all');
        $search  = trim((string) $request->input('search', ''));

        $query = LicenseLogsHeartbeat::with('licenseCompany.company')
            ->orderByDesc('heartbeat_at');

        if ($filter === 'ok') {
            $query->whereIn('status', ['success', 'verified']);
        } elseif ($filter === 'failed') {
            $query->whereNotIn('status', ['success', 'verified']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('app_code', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhere('failure_reason', 'like', "%{$search}%")
                  ->orWhereHas('licenseCompany.company', function ($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $page = $query->paginate($perPage);

        $rows = collect($page->items())->map(function ($h) {
            return [
                'company'     => optional(optional($h->licenseCompany)->company)->name
                                 ?? optional($h->licenseCompany)->label ?? '—',
                'app_code'    => $h->app_code,
                'ip_address'  => $h->ip_address,
                'app_version' => $h->app_version,
                'status'      => $h->status,
                'ok'          => in_array($h->status, ['success', 'verified'], true),
                'reason'      => $h->failure_reason,
                'at'          => $h->heartbeat_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success'    => true,
            'data'       => $rows,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
            ],
        ]);
    }

    // ── Per-installation diagnosis (shared by Monitor + License page) ─────────

    /**
     * GET /heartbeat-monitor/diagnose/{hash}
     * Returns the server-side diagnosis for one installation: the most likely
     * reason that client would need to re-activate, plus a full checklist.
     */
    public function diagnose(string $hash): JsonResponse
    {
        $ids = Hashids::decode($hash);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'Installation tidak ditemukan.'], 404);
        }

        $inst = LicenseInstallation::with('licenseCompany.company')->find($ids[0]);
        if (! $inst) {
            return response()->json(['success' => false, 'message' => 'Installation tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->diagnoseInstallation($inst)]);
    }

    /**
     * Build the diagnosis for a single installation. Looks at the things ONLY
     * the server knows: license status/expiry, installation revoke/blacklist,
     * heartbeat freshness vs token TTL, the latest heartbeat result + reason,
     * slot usage, and suspicious events.
     */
    protected function diagnoseInstallation(LicenseInstallation $inst): array
    {
        $interval  = (int) MasterConfig::get('licensing.heartbeat_interval', 3600);
        $ttlDays   = (int) config('licensing.offline_token.ttl_days', 7);
        $staleSecs = max(120, (int) ($interval * 1.5));
        $ttlSecs   = max($staleSecs + 60, $ttlDays * 86400);

        $company   = $inst->licenseCompany;
        $last      = $inst->last_heartbeat_at;
        $age       = $last ? max(0, now()->getTimestamp() - $last->getTimestamp()) : null;
        $health    = $this->classify($age, $staleSecs, $ttlSecs);
        $daysSince = $age !== null ? (int) floor($age / 86400) : null;

        $latest = LicenseLogsHeartbeat::where('installation_id', $inst->id)
            ->orderByDesc('heartbeat_at')->first();

        $suspicious = LicenseLogsSuspicious::where('installation_id', $inst->id)
            ->where('is_reviewed', false)->orderByDesc('occurred_at')->get();

        $activeCount = $company
            ? LicenseInstallation::where('license_company_id', $company->id)->where('status', 'active')->count()
            : 0;
        $maxInstalls = (int) ($company->max_installations ?? 0);

        $checks  = [];
        $verdict = null; // first non-pass becomes the headline cause

        // 1. License status (server-side)
        $licStatus = $company->status ?? 'unknown';
        if ($company && in_array($licStatus, ['suspended', 'cancelled'], true)) {
            $checks[] = $this->c('license_status', 'Status lisensi di server', 'fail',
                "Lisensi berstatus \"{$licStatus}\". Server menolak refresh token → klien akan terus diminta aktivasi ulang.",
                'Aktifkan kembali (reinstate) lisensi ini agar klien bisa berfungsi.');
            $verdict = $verdict ?? $this->v('danger', 'Lisensi di-' . $licStatus . ' di server',
                "Selama lisensi berstatus \"{$licStatus}\", token tidak akan diperpanjang dan klien akan minta aktivasi ulang.",
                'Reinstate lisensi di halaman Licenses.');
        } else {
            $checks[] = $this->c('license_status', 'Status lisensi di server', 'pass',
                'Lisensi aktif di server.', null);
        }

        // 2. License expiry
        if ($company && $company->expires_at) {
            if ($company->expires_at->isPast()) {
                $checks[] = $this->c('license_expiry', 'Masa berlaku lisensi', 'fail',
                    'Lisensi sudah KEDALUWARSA pada ' . $company->expires_at->format('d M Y H:i') . '.',
                    'Perpanjang (renew) lisensi agar klien bisa aktif kembali.');
                $verdict = $verdict ?? $this->v('danger', 'Lisensi kedaluwarsa',
                    'Lisensi berakhir ' . $company->expires_at->format('d M Y') . '. Klien tidak bisa memperpanjang token.',
                    'Renew lisensi di halaman Licenses.');
            } else {
                $checks[] = $this->c('license_expiry', 'Masa berlaku lisensi', 'pass',
                    'Berlaku sampai ' . $company->expires_at->format('d M Y') . '.', null);
            }
        } else {
            $checks[] = $this->c('license_expiry', 'Masa berlaku lisensi', 'pass',
                'Lisensi LIFETIME (tidak ada tanggal kedaluwarsa).', null);
        }

        // 3. Installation status
        if (in_array($inst->status, ['revoked', 'blacklisted'], true)) {
            $checks[] = $this->c('installation_status', 'Status instalasi', 'fail',
                "Instalasi ini berstatus \"{$inst->status}\""
                    . ($inst->revoke_reason ? " ({$inst->revoke_reason})" : '') . '. Token tidak akan diperpanjang.',
                'Hapus revoke/blacklist jika instalasi ini sah.');
            $verdict = $verdict ?? $this->v('danger', 'Instalasi di-' . $inst->status,
                'Instalasi ini ditolak server, jadi klien akan minta aktivasi ulang.',
                'Cek halaman Installations untuk memulihkan jika perlu.');
        } else {
            $checks[] = $this->c('installation_status', 'Status instalasi', 'pass',
                'Instalasi aktif.', null);
        }

        // 4. Heartbeat freshness vs token TTL
        $hbMsg = $last
            ? ('Heartbeat sukses terakhir ' . $last->diffForHumans() . ' (' . $last->format('d M Y H:i') . ').')
            : 'Belum pernah ada heartbeat sukses.';
        if ($health === 'expired') {
            $checks[] = $this->c('heartbeat', 'Kesegaran heartbeat', 'fail',
                $hbMsg . " Sudah {$daysSince} hari (> TTL {$ttlDays} hari) → token offline klien kemungkinan SUDAH kedaluwarsa.",
                'Penyebab di sisi klien: scheduler/cron mati atau server lisensi tak terjangkau dari klien. Cek halaman Diagnostics di klien.');
            $verdict = $verdict ?? $this->v('danger', 'Token klien kemungkinan sudah kedaluwarsa',
                "Klien tidak heartbeat {$daysSince} hari (lebih dari TTL token {$ttlDays} hari). Token offline-nya habis sehingga aplikasi kembali ke halaman aktivasi.",
                'Di sisi klien: pastikan cron "schedule:run" hidup dan koneksi ke server lisensi lancar. Token akan otomatis diperpanjang begitu heartbeat jalan lagi.');
        } elseif ($health === 'late') {
            $checks[] = $this->c('heartbeat', 'Kesegaran heartbeat', 'warn',
                $hbMsg . ' Klien telat heartbeat tapi token MASIH berlaku.',
                'Pantau; jika berlanjut sampai > TTL, klien akan minta aktivasi ulang.');
        } elseif ($health === 'never') {
            $checks[] = $this->c('heartbeat', 'Kesegaran heartbeat', 'warn',
                'Instalasi tercatat tapi belum pernah heartbeat sukses.', 'Pastikan klien sudah aktivasi dan cron jalan.');
        } else {
            $checks[] = $this->c('heartbeat', 'Kesegaran heartbeat', 'pass', $hbMsg, null);
        }

        // 5. Latest heartbeat result
        if ($latest) {
            $ok = in_array($latest->status, ['success', 'verified'], true);
            $checks[] = $this->c('last_result', 'Hasil heartbeat terakhir', $ok ? 'pass' : 'fail',
                'Status: ' . strtoupper((string) $latest->status)
                    . ($latest->failure_reason ? ' — ' . $latest->failure_reason : '')
                    . ' (' . optional($latest->heartbeat_at)->format('d M Y H:i') . ').',
                $ok ? null : 'Lihat alasan kegagalan; bisa jadi fingerprint berubah atau lisensi ditolak.');
            if (! $ok) {
                $verdict = $verdict ?? $this->v('danger', 'Heartbeat terakhir gagal',
                    'Server menolak heartbeat terakhir: ' . ($latest->failure_reason ?: $latest->status) . '.',
                    'Periksa detail penolakan; mungkin perlu aktivasi ulang atau pembersihan instalasi.');
            }
        }

        // 6. Installation slots
        if ($company && $maxInstalls > 0) {
            $full = $activeCount >= $maxInstalls;
            $checks[] = $this->c('slots', 'Slot instalasi', $full ? 'warn' : 'pass',
                "{$activeCount} / {$maxInstalls} slot terpakai.",
                $full ? 'Slot penuh — instalasi baru (mis. setelah pindah server) akan ditolak sampai slot lama di-revoke.' : null);
            if ($full && $health === 'expired') {
                // A common real-world trap: server moved, old slot still occupies the cap.
                $verdict = $verdict ?? $this->v('warning', 'Slot instalasi penuh',
                    'Semua slot terpakai. Jika klien pindah server, aktivasi di mesin baru akan ditolak.',
                    'Revoke slot/usage lama agar mesin baru bisa aktivasi.');
            }
        }

        // 7. Suspicious events
        if ($suspicious->count() > 0) {
            $top = $suspicious->first();
            $checks[] = $this->c('suspicious', 'Event mencurigakan', 'warn',
                $suspicious->count() . ' event belum direview (terbaru: ' . ($top->event_type ?? 'unknown') . ').',
                'Bisa berarti fingerprint berubah/clone. Tinjau di halaman Installations.');
            $verdict = $verdict ?? $this->v('warning', 'Ada aktivitas mencurigakan',
                $suspicious->count() . ' event belum direview — kemungkinan fingerprint berubah (pindah/clone server).',
                'Tinjau event; jika pemindahan sah, revoke instalasi lama lalu aktivasi ulang di mesin baru.');
        }

        // Healthy fallback
        if (! $verdict) {
            $verdict = $this->v('success', 'Tidak ada kendala di sisi server',
                'Lisensi aktif, instalasi sehat, dan heartbeat normal. Jika klien tetap minta aktivasi, penyebabnya ada di sisi klien (fingerprint berubah, jam server, atau APP_KEY).',
                'Buka halaman Diagnostics di aplikasi klien untuk detail sisi-klien.');
        }

        return [
            'installation' => [
                'company'     => optional(optional($inst->licenseCompany)->company)->name
                                 ?? optional($inst->licenseCompany)->label ?? '—',
                'app_code'    => $inst->app_code,
                'domain'      => $inst->domain,
                'ip_address'  => $inst->ip_address,
                'app_version' => $inst->app_version,
                'hostname'    => $inst->hostname,
                'status'      => $inst->status,
                'health'      => $health,
                'last_heartbeat' => $last?->toIso8601String(),
                'days_since'  => $daysSince,
                'client_diagnostics_url' => $this->clientDiagnosticsUrl($inst->domain),
            ],
            'verdict' => $verdict,
            'checks'  => $checks,
        ];
    }

    protected function c(string $key, string $label, string $status, string $message, ?string $hint): array
    {
        return compact('key', 'label', 'status', 'message', 'hint');
    }

    protected function v(string $level, string $title, string $message, ?string $hint): array
    {
        return compact('level', 'title', 'message', 'hint');
    }

    /**
     * Build a deep link to the client's own diagnostics page from its domain.
     * The client exposes /license/diagnostics unauthenticated (reachable even
     * when unlicensed), so the admin can jump straight there to see the
     * client-only signals (token exp, fingerprint, cron liveness).
     */
    protected function clientDiagnosticsUrl(?string $domain): ?string
    {
        if (! $domain) {
            return null;
        }

        $host = trim(preg_replace('#^https?://#i', '', $domain), '/ ');
        if ($host === '') {
            return null;
        }

        return 'https://' . $host . self::CLIENT_DIAGNOSTICS_PATH;
    }

    /**
     * Classify an installation's heartbeat freshness.
     *   online  — pinged within 1.5x the interval
     *   late    — silent past that but within the token TTL (still licensed)
     *   expired — silent longer than the token TTL -> client's offline token has
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
