<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterConfig;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/platform/v1/client-config         — full config bundle
 * GET /api/platform/v1/client-config/version — lightweight version hash
 *
 * Public, no auth. Returns the runtime config that client apps (ermv3,
 * future products) need to operate the licensing client. None of these
 * are secrets — they're just defaults the server controls so admins can
 * tune behavior without redeploying every client.
 *
 * Client polling pattern:
 *   1. Cron tiap menit memanggil `/version` (sangat murah)
 *   2. Kalau hash berubah dari yang ter-cache, refetch full config
 *   3. Kalau sama, skip (no-op)
 *
 * Pola ini bikin perubahan interval di gemilang langsung kepick up di client
 * dalam waktu paling lama 1 menit (sesuai tick cron).
 */
class ClientConfigController extends Controller
{
    /**
     * Keys yang berkontribusi ke version hash. Wajib diurutkan supaya
     * hash deterministik. Tambahkan key baru di sini kalau diperlukan.
     */
    protected const HASH_KEYS = [
        'licensing.issuer',
        'licensing.heartbeat_enabled',
        'licensing.heartbeat_interval',
        'licensing.heartbeat_retry_limit',
        'licensing.warning_days',
        'licensing.grace_period_days',
        'licensing.timeout',
        'licensing.cache_ttl',
        'licensing.clock_skew_seconds',
        'licensing.debug',
    ];

    public function show(): JsonResponse
    {
        $payload = $this->buildPayload();

        return response()->json([
            'success' => true,
            'data'    => $payload,
        ]);
    }

    /**
     * GET /api/platform/v1/client-config/version
     *
     * Mengembalikan hash dari semua nilai yang relevan. Client polling endpoint
     * ini tiap menit; kalau hash beda dari yang ter-cache lokal, baru refetch
     * full config. Sangat murah (single SELECT WHERE IN per request).
     */
    public function version(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'version' => $this->computeVersion(),
                'now'     => now()->toIso8601String(),
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    protected function buildPayload(): array
    {
        $get = fn (string $key, mixed $default) => MasterConfig::get($key, $default);

        return [
            'issuer'                => $get('licensing.issuer', config('licensing.offline_token.issuer', 'gemilang-inti-teknologi')),

            // Heartbeat
            'heartbeat_enabled'     => (bool) $get('licensing.heartbeat_enabled', true),
            'heartbeat_interval'    => (int)  $get('licensing.heartbeat_interval', 3600),
            'heartbeat_retry_limit' => (int)  $get('licensing.heartbeat_retry_limit', 3),
            'warning_days'          => (int)  $get('licensing.warning_days', 3),
            'grace_period_days'     => (int)  $get('licensing.grace_period_days', 7),

            // Network / token
            'timeout'               => (int)  $get('licensing.timeout', 30),
            'cache_ttl'             => (int)  $get('licensing.cache_ttl', 3600),
            'clock_skew_seconds'    => (int)  $get('licensing.clock_skew_seconds', 60),

            // Misc
            'debug'                 => (bool) $get('licensing.debug', false),

            // Version hash supaya client tahu kapan harus refetch
            'version'               => $this->computeVersion(),

            // Cache hint — turun dari 6 jam ke 5 menit. Hard limit jaring
            // pengaman; mekanisme utama adalah polling /version per menit.
            'cache_until'           => now()->addMinutes(5)->toIso8601String(),
        ];
    }

    /**
     * Hash deterministik dari semua nilai config yang relevan.
     * Berubah otomatis kalau salah satu key di HASH_KEYS di-update di tabel
     * master_configs.
     */
    protected function computeVersion(): string
    {
        $rows = MasterConfig::whereIn('config_key', self::HASH_KEYS)
            ->orderBy('config_key')
            ->get(['config_key', 'config_value', 'updated_at']);

        $material = $rows
            ->map(fn ($r) => $r->config_key . ':' . $r->config_value . ':' . $r->updated_at?->timestamp)
            ->implode('|');

        return substr(hash('sha256', $material), 0, 16);
    }
}
