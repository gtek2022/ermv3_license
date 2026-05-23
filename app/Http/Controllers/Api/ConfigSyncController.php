<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use App\Models\LicenseNonce;
use App\Models\MasterAppConfig;
use App\Models\MasterConfig;
use App\Models\MasterFeatureFlag;
use App\Services\Licensing\SigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/platform/v1/config-sync
 *
 * ERMv3 calls this to fetch the latest configs, feature flags, and
 * enforcement policy. Response is Ed25519-signed.
 *
 * Request:
 *   { app_code, license_key, fingerprint, current_config_version, timestamp, nonce }
 *
 * Response:
 *   { success, configs, feature_flags, enforcement_policy, version, signed_payload, expires_at }
 */
class ConfigSyncController extends Controller
{
    public function __construct(protected SigningService $signer) {}

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_code'               => 'required|string|max:50',
            'license_key'            => 'required|string',
            'fingerprint'            => 'required|string|max:64',
            'current_config_version' => 'nullable|string',
            'timestamp'              => 'required|string',
            'nonce'                  => 'required|string|max:64',
        ]);

        // ── Timestamp validation (±60 seconds) ───────────────────────────────
        try {
            $ts = \Carbon\Carbon::parse($data['timestamp']);
        } catch (\Throwable) {
            return $this->error('INVALID_TIMESTAMP', 'Invalid timestamp format.', 422);
        }

        if (abs($ts->diffInSeconds(now())) > 60) {
            return $this->error('TIMESTAMP_EXPIRED', 'Request timestamp is outside the ±60s window.', 422);
        }

        // ── Nonce replay protection ───────────────────────────────────────────
        if (LicenseNonce::isUsed($data['nonce'])) {
            return $this->error('NONCE_REPLAYED', 'Nonce has already been used.', 409);
        }

        // ── License validation ────────────────────────────────────────────────
        $license = LicenseCompany::findByKey($data['license_key']);

        if (! $license || ! $license->isActive()) {
            return $this->error('INVALID_LICENSE', 'License is invalid or inactive.', 403);
        }

        // Check app is licensed
        $licenseApp = $license->licenseApps()
            ->where('app_code', $data['app_code'])
            ->where('status', 'active')
            ->first();

        if (! $licenseApp) {
            return $this->error('APP_NOT_LICENSED', 'This app is not covered by the license.', 403);
        }

        // ── Consume nonce ─────────────────────────────────────────────────────
        LicenseNonce::consume($data['nonce']);

        // ── Build config payload ──────────────────────────────────────────────
        $configs = $this->buildConfigs($data['app_code']);
        $flags   = $this->buildFeatureFlags($data['app_code']);
        $policy  = $this->buildEnforcementPolicy($license->id);
        $version = 'v' . crc32(json_encode($configs) . json_encode($flags));

        $payload = [
            'configs'            => $configs,
            'feature_flags'      => $flags,
            'enforcement_policy' => $policy,
            'version'            => $version,
            'expires_at'         => now()->addHours(2)->toIso8601String(),
            'issued_at'          => now()->toIso8601String(),
        ];

        // ── Sign the response ─────────────────────────────────────────────────
        try {
            $signed = $this->signer->signPayload($payload);
        } catch (\Throwable $e) {
            return $this->error('SIGNING_FAILED', 'Failed to sign response.', 500);
        }

        return response()->json([
            'success'      => true,
            'data'         => $payload,
            'signed_payload' => $signed,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildConfigs(string $appCode): array
    {
        // Global public configs
        $global = MasterConfig::where('is_public', true)
            ->get()
            ->mapWithKeys(fn ($c) => [$c->config_key => $c->getValue()])
            ->toArray();

        // App-specific configs
        $appConfigs = MasterAppConfig::where('app_code', $appCode)
            ->get()
            ->mapWithKeys(fn ($c) => [$c->config_key => $c->getValue()])
            ->toArray();

        return array_merge($global, $appConfigs);
    }

    private function buildFeatureFlags(string $appCode): array
    {
        return MasterFeatureFlag::where(function ($q) use ($appCode) {
            $q->where('app_scope', $appCode)->orWhere('app_scope', '*');
        })
        ->get()
        ->mapWithKeys(fn ($f) => [$f->feature_key => $f->enabled])
        ->toArray();
    }

    private function buildEnforcementPolicy(int $licenseId): array
    {
        // Per-license overrides stored in master_app_configs with app_code = 'license_{id}'
        $overrideScope = 'license_' . $licenseId;

        $get = fn (string $key, mixed $default) => MasterAppConfig::getForApp($overrideScope, $key)
            ?? MasterConfig::get($key, $default);

        return [
            'heartbeat_interval'       => (int) $get('heartbeat_interval', 3600),
            'heartbeat_retry_limit'    => (int) $get('heartbeat_retry_limit', 3),
            'warning_days_before_lockout' => (int) $get('warning_days_before_lockout', 3),
            'grace_period_days'        => (int) $get('grace_period_days', 7),
            'warning_banner_message'   => $get('warning_banner_message', 'License verification failed.'),
            'lock_modal_message'       => $get('lock_modal_message', 'License verification required.'),
            'force_revalidation'       => (bool) $get('force_revalidation', false),
            'maintenance_mode'         => (bool) $get('maintenance_mode', false),
            'maintenance_message'      => $get('maintenance_message', ''),
            'minimum_app_version'      => $get('minimum_app_version', '0.0.0'),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => $message], $status);
    }
}
