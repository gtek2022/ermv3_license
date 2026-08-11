<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseCompany;
use App\Models\LicenseFeatureActivation;
use App\Models\LicenseInstallation;
use App\Models\LicenseNonce;
use App\Models\MasterAppConfig;
use App\Models\MasterAppFeature;
use App\Models\MasterConfig;
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
 *   { success, configs, licensed_features, feature_catalog, enforcement_policy, version,
 *     signed_payload, expires_at }
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
            'installation_uuid'      => 'nullable|string|max:64',
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
        //
        // feature_flags used to be built and shipped here. It is gone: nothing on any client ever read
        // it - getFeatureFlags() exists in three of them with no callers - and master_feature_flags
        // never held a single row. Clients guard the key with isset(), so dropping it changes nothing
        // for them beyond no longer writing a file they never opened.
        $installationUuid = $data['installation_uuid'] ?? null;
        $configs          = $this->buildConfigs($data['app_code']);
        $licensedFeatures = $this->buildLicensedFeatures($licenseApp);
        $featureCatalog   = $this->buildFeatureCatalog($data['app_code'], $installationUuid);
        $policy           = $this->buildEnforcementPolicy($license->id);
        $version          = 'v' . crc32(json_encode($configs) . json_encode($licensedFeatures) . json_encode($featureCatalog));

        $payload = [
            'configs'             => $configs,
            'licensed_features'   => $licensedFeatures,
            'feature_catalog'     => $featureCatalog,
            'enforcement_policy'  => $policy,
            'version'             => $version,
            'expires_at'          => now()->addHours(2)->toIso8601String(),
            'issued_at'           => now()->toIso8601String(),
        ];

        /*
         * Sign the response if a signing key allows it, and answer either way.
         *
         * This used to return 500 SIGNING_FAILED when signing threw, and signing threw on every
         * request because App\Models\LicenseSigningKey did not exist - so no client has ever received
         * a feature catalogue or a list of entitlements from this endpoint. Refusing to answer is the
         * worse failure of the two: the licence was already checked above, the transport is TLS, and
         * no client verifies the envelope today. The reason is reported in the response so a broken
         * signing key is visible rather than silent.
         */
        $signed = null;
        $signingError = null;

        try {
            $signed = $this->signer->signPayload($payload);
        } catch (\Throwable $e) {
            $signingError = $e->getMessage();
            \Illuminate\Support\Facades\Log::warning('[ConfigSync] could not sign payload: ' . $e->getMessage());
        }

        return response()->json(array_filter([
            'success'        => true,
            'data'           => $payload,
            'signed_payload' => $signed,
            'signing_error'  => $signingError,
        ], fn ($value) => $value !== null));
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

    /**
     * Build the licensed features for this specific license_app.
     *
     * Returns: { feature_key => { licensed: bool, valid_until: string|null } }
     *
     * Logic:
     * - If license_app has NO feature records at all → all master features are licensed (backward compat)
     * - If license_app HAS feature records → only those features are licensed
     */
    private function buildLicensedFeatures(\App\Models\LicenseApp $licenseApp): array
    {
        $licensedFeatures = $licenseApp->features()->get();

        // No feature records = all features licensed (app-level license, no feature restriction)
        if ($licensedFeatures->isEmpty()) {
            return \App\Models\MasterAppFeature::where('app_code', $licenseApp->app_code)
                ->where('is_active', true)
                ->get()
                ->mapWithKeys(fn ($f) => [
                    $f->feature_key => [
                        'licensed'    => true,
                        'valid_until' => null,
                        'status'      => 'active',
                    ]
                ])
                ->toArray();
        }

        // Has feature records = only licensed features are active
        $result = [];

        // First, mark all master features as NOT licensed
        \App\Models\MasterAppFeature::where('app_code', $licenseApp->app_code)
            ->where('is_active', true)
            ->get()
            ->each(function ($f) use (&$result) {
                $result[$f->feature_key] = [
                    'licensed'    => false,
                    'valid_until' => null,
                    'status'      => 'unlicensed',
                ];
            });

        // Then override with licensed ones
        foreach ($licensedFeatures as $lf) {
            $isActive = $lf->status === 'active'
                && (! $lf->valid_until || $lf->valid_until->isFuture());

            $result[$lf->feature_key] = [
                'licensed'    => $isActive,
                'valid_until' => $lf->valid_until?->toIso8601String(),
                'status'      => $lf->status,
            ];
        }

        return $result;
    }

    private function buildEnforcementPolicy(int $licenseId): array
    {
        // 1. Per-license override (paling spesifik)
        //    Disimpan oleh admin via /licenses/{hash}/policy form di
        //    license_companies.meta.policy.{heartbeat_tolerance, warning_days}
        $licenseMeta = \App\Models\LicenseCompany::find($licenseId)?->meta ?? [];
        $perLicense  = $licenseMeta['policy'] ?? [];

        // 2. Global default dari master_configs
        $get = fn (string $key, mixed $default) => MasterConfig::get($key, $default);

        // Merge: per-license override > global config > hard-coded fallback.
        // Note: form admin pakai key 'heartbeat_tolerance' tapi response client
        // pakai 'heartbeat_retry_limit' (kompatibilitas dengan client legacy).
        $tolerance = $perLicense['heartbeat_tolerance']
            ?? $get('heartbeat_retry_limit', 3);

        $warnDays = $perLicense['warning_days']
            ?? $get('warning_days_before_lockout', 3);

        return [
            'heartbeat_interval'          => (int) $get('heartbeat_interval', 3600),
            'heartbeat_retry_limit'       => (int) $tolerance,
            'warning_days_before_lockout' => (int) $warnDays,
            'grace_period_days'           => (int) $get('grace_period_days', 7),
            'warning_banner_message'      => $get('warning_banner_message', 'License verification failed.'),
            'lock_modal_message'          => $get('lock_modal_message', 'License verification required.'),
            'force_revalidation'          => (bool) $get('force_revalidation', false),
            'maintenance_mode'            => (bool) $get('maintenance_mode', false),
            'maintenance_message'         => $get('maintenance_message', ''),
            'minimum_app_version'         => $get('minimum_app_version', '0.0.0'),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => $message], $status);
    }

    /**
     * Build the feature catalog — all features for this app with their type and activation status.
     *
     * Returns: {
     *   feature_key => {
     *     name: string,
     *     category: string,
     *     is_active: bool,           // admin toggle (free features)
     *     requires_license: bool,    // true = needs FLK-XXXX key
     *     activated: bool,           // true = this installation has a live feature licence
     *     expires_at: string|null,   // ISO-8601 deadline, null = perpetual
     *     days_remaining: int|null,  // whole days left, null = perpetual, 0 = lapsed
     *     expired: bool,             // the term ran out (activated is false in that case too)
     *   }
     * }
     *
     * `activated` is the field clients gate on, and an activation whose term has run out does not
     * count towards it - that is what makes a lapsed FLK re-lock the module. The three validity
     * fields are carried alongside so a client can say *why* it locked, and can warn while the term
     * is still running rather than only reacting once it is gone.
     */
    private function buildFeatureCatalog(string $appCode, ?string $installationUuid): array
    {
        $features = MasterAppFeature::where('app_code', $appCode)->get();

        // This installation's activations, keyed by feature. Fetched as models rather than plucked
        // keys because the deadline travels with each one.
        $activations = collect();
        if ($installationUuid) {
            $activations = LicenseFeatureActivation::where('app_code', $appCode)
                ->where('installation_uuid', $installationUuid)
                ->where('status', 'active')
                ->get()
                ->keyBy('feature_key');
        }

        return $features->mapWithKeys(function ($f) use ($activations) {
            $activation = $activations->get($f->feature_key);

            // Free features carry no term and are always "activated"; only the admin toggle
            // (is_active, read separately by the client) can take one away.
            if (! $f->requires_license) {
                return [
                    $f->feature_key => [
                        'name'             => $f->name,
                        'category'         => $f->category,
                        'is_active'        => $f->is_active,
                        'requires_license' => false,
                        'activated'        => true,
                        'expires_at'       => null,
                        'days_remaining'   => null,
                        'expired'          => false,
                    ],
                ];
            }

            return [
                $f->feature_key => [
                    'name'             => $f->name,
                    'category'         => $f->category,
                    'is_active'        => $f->is_active,
                    'requires_license' => true,
                    // Live means active by status AND inside its term. No activation at all and a
                    // lapsed one both land on false here; `expired` is what tells them apart.
                    'activated'        => (bool) $activation?->isCurrentlyActive(),
                    'expires_at'       => $activation?->expires_at?->toIso8601String(),
                    'days_remaining'   => $activation?->daysRemaining(),
                    'expired'          => (bool) $activation?->isExpired(),
                ],
            ];
        })->toArray();
    }
}
