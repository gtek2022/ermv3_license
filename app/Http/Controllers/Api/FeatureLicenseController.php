<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseFeatureActivation;
use App\Models\MasterAppFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feature License API — called by ERMv3 to activate/deactivate feature licenses.
 *
 * POST /api/platform/v1/feature/activate
 * POST /api/platform/v1/feature/deactivate
 * GET  /api/platform/v1/feature/status
 */
class FeatureLicenseController extends Controller
{
    /**
     * Activate a feature license on an installation.
     *
     * Request: { app_code, feature_license_key, installation_uuid, fingerprint }
     * Response: { success, feature_key, feature_name, status, expires_at, days_remaining, expired }
     *
     * When the feature carries a term (master_app_features.license_duration_days) the deadline is
     * stamped here as now() + term. Redeeming the same key again renews from this moment rather than
     * adding to whatever was left: "activate" is a fresh term, and a customer who pastes their key
     * twice should not quietly end up with sixty days on a thirty-day licence. Extending an existing
     * term is a separate decision and belongs to an administrator, not to whoever holds the key.
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_code'            => 'required|string|max:50',
            'feature_license_key' => 'required|string',
            'installation_uuid'   => 'required|string|max:64',
            'fingerprint'         => 'required|string|max:64',
        ]);

        // Find the feature by license key
        $feature = MasterAppFeature::findByLicenseKey($data['feature_license_key']);

        if (! $feature) {
            return $this->error('INVALID_FEATURE_KEY', 'Feature license key is invalid.', 404);
        }

        if ($feature->app_code !== $data['app_code']) {
            return $this->error('APP_MISMATCH', 'Feature does not belong to this app.', 403);
        }

        if (! $feature->requires_license) {
            return $this->error('NOT_LICENSED_FEATURE', 'This feature does not require a license key.', 400);
        }

        if (! $feature->is_active) {
            return $this->error('FEATURE_DISABLED', 'This feature is currently disabled by the administrator.', 403);
        }

        /*
         * Apakah lisensi instalasi ini memang mencakup fitur tersebut?
         *
         * Menukarkan FLK dan berhak atas modulnya adalah dua hal berbeda: FLK mengikat modul ke satu
         * instalasi, entitlement (license_app_features) menyatakan lisensinya membeli modul itu. Client
         * mewajibkan keduanya, dan itu memang disengaja.
         *
         * Yang tidak disengaja adalah endpoint ini dulu mencatat aktivasi tanpa melihat entitlement.
         * Akibatnya kunci yang tidak akan pernah membuka apa pun tetap "berhasil": halaman admin
         * melaporkan "1 instalasi aktif" di baris yang Status Lisensi-nya "✗ Tidak", dan pelanggan
         * memasukkan kunci yang benar lalu tetap melihat modulnya terkunci. Dua-duanya membuat FLK
         * terlihat rusak padahal justru bekerja sesuai rancangan.
         *
         * Jadi ditolak lebih awal, dengan alasan yang menyebut tindakan yang benar. Hanya ditegakkan
         * kalau lisensinya memang bisa ditemukan DAN memang mendeklarasikan batasan fitur - aturan
         * "tanpa baris berarti semua berlisensi" dipertahankan sama seperti di
         * ConfigSyncController::buildLicensedFeatures(), dan instalasi yang belum terdaftar tidak
         * dihalangi supaya aktivasi pertama kali tidak ikut terkunci.
         */
        $licenseAppId = $this->resolveLicenseAppId($data['app_code'], $data['installation_uuid'], $data['fingerprint']);

        if ($licenseAppId) {
            $entitlements = \App\Models\LicenseAppFeature::where('license_app_id', $licenseAppId)->get();

            if ($entitlements->isNotEmpty()) {
                $entitlement = $entitlements->firstWhere('feature_key', $feature->feature_key);

                if (! $entitlement || ! $entitlement->isActive()) {
                    return $this->error(
                        'FEATURE_NOT_ENTITLED',
                        'Modul "' . $feature->name . '" belum termasuk dalam lisensi instalasi ini, '
                            . 'jadi FLK-nya tidak akan membukanya. Hubungi Gemilang untuk menambahkan '
                            . 'modul ini ke lisensi Anda terlebih dahulu.',
                        403
                    );
                }
            }
        }

        // Create or update activation record
        $activation = LicenseFeatureActivation::updateOrCreate(
            [
                'feature_key'      => $feature->feature_key,
                'installation_uuid' => $data['installation_uuid'],
            ],
            [
                'app_code'                  => $data['app_code'],
                'feature_license_key_hash'  => MasterAppFeature::hashKey($data['feature_license_key']),
                'fingerprint'               => $data['fingerprint'],
                'status'                    => 'active',
                'activated_at'              => now(),
                // null for a perpetual key, which is what every FLK issued before terms existed is.
                'expires_at'                => $feature->expiryFromNow(),
                'revoked_at'                => null,
            ]
        );

        $message = 'Feature "' . $feature->name . '" activated successfully.';

        if ($activation->expires_at) {
            $message .= ' Berlaku ' . $feature->license_duration_days . ' hari, sampai '
                . $activation->expires_at->translatedFormat('d M Y H:i') . '.';
        }

        return response()->json(array_merge([
            'success'      => true,
            'feature_key'  => $feature->feature_key,
            'feature_name' => $feature->name,
            'status'       => 'active',
            'message'      => $message,
        ], $activation->validityPayload()));
    }

    /**
     * Which licence is this activation request coming from?
     *
     * Awkward because the request does not carry the licence key - it has app_code, the FLK, an
     * installation uuid and a fingerprint - so the licence has to be inferred. Three attempts, in
     * descending order of confidence:
     *
     *   1. the installation uuid, which is what the client believes it is
     *   2. the fingerprint, for when the uuid has drifted
     *   3. the app_code, but only when exactly one active licence uses it
     *
     * Step 2 exists because those two identifiers do drift apart in practice. On the crm production
     * install the registered installation carries uuid 8810071b / fingerprint 18d0cd2e while its
     * feature activations carry 57e686a0 / bf5ae65d: the client mints a fresh uuid whenever its
     * encrypted state cannot be read back, and the fingerprint moves with APP_KEY and the database
     * identity, so a key rotation or a cleared storage directory silently orphans the installation
     * record while the app keeps working. Matching on only one of the two would have skipped the
     * check on exactly the installation that prompted it.
     *
     * Null means "could not tell", and the caller then allows the activation. That is deliberate: this
     * is a guard-rail that turns a silently useless activation into a clear refusal, not the boundary
     * that keeps an unlicensed module shut. The boundary is the client requiring both the activation
     * and the entitlement, which holds regardless of what this returns.
     */
    private function resolveLicenseAppId(string $appCode, string $installationUuid, string $fingerprint): ?int
    {
        $installation = \App\Models\LicenseInstallation::where('app_code', $appCode)
            ->where('installation_uuid', $installationUuid)
            ->first();

        if (! $installation) {
            $installation = \App\Models\LicenseInstallation::where('app_code', $appCode)
                ->where('fingerprint', $fingerprint)
                ->first();
        }

        if ($installation && $installation->license_app_id) {
            return (int) $installation->license_app_id;
        }

        // Unambiguous single-tenant fallback. Skipped when more than one licence shares the app_code,
        // because guessing between customers is worse than not checking.
        $candidates = \App\Models\LicenseApp::where('app_code', $appCode)
            ->where('status', 'active')
            ->pluck('id');

        return $candidates->count() === 1 ? (int) $candidates->first() : null;
    }

    /**
     * Deactivate a feature license on an installation.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_code'          => 'required|string|max:50',
            'feature_key'       => 'required|string|max:100',
            'installation_uuid' => 'required|string|max:64',
        ]);

        $activation = LicenseFeatureActivation::where('feature_key', $data['feature_key'])
            ->where('installation_uuid', $data['installation_uuid'])
            ->where('app_code', $data['app_code'])
            ->first();

        if (! $activation) {
            return $this->error('NOT_FOUND', 'Feature activation not found.', 404);
        }

        $activation->update([
            'status'     => 'revoked',
            'revoked_at' => now(),
        ]);

        return response()->json([
            'success'     => true,
            'feature_key' => $data['feature_key'],
            'status'      => 'revoked',
        ]);
    }

    /**
     * Get status of all feature activations for an installation.
     * Called during config sync to include feature activation status.
     *
     * `activated_features` stays a flat list of keys, because pds reads it that way
     * (App\Services\Licensing\LicenseClient) and this is the shape it expects. What changed is what
     * qualifies: an activation whose term has run out is no longer in the list, so pds re-locks on
     * expiry without needing a change of its own.
     *
     * `features` is added alongside with the term detail, for clients that want to warn before the
     * deadline rather than only react after it.
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_code'          => 'required|string|max:50',
            'installation_uuid' => 'required|string|max:64',
        ]);

        $activations = LicenseFeatureActivation::where('app_code', $data['app_code'])
            ->where('installation_uuid', $data['installation_uuid'])
            ->live()
            ->get();

        return response()->json([
            'success'            => true,
            'activated_features' => $activations->pluck('feature_key')->values()->toArray(),
            'features'           => $activations
                ->mapWithKeys(fn (LicenseFeatureActivation $a) => [
                    $a->feature_key => $a->validityPayload(),
                ])
                ->toArray(),
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => $message], $status);
    }
}
