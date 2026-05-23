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
     * Response: { success, feature_key, feature_name, status }
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
                'revoked_at'                => null,
            ]
        );

        return response()->json([
            'success'      => true,
            'feature_key'  => $feature->feature_key,
            'feature_name' => $feature->name,
            'status'       => 'active',
            'message'      => 'Feature "' . $feature->name . '" activated successfully.',
        ]);
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
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_code'          => 'required|string|max:50',
            'installation_uuid' => 'required|string|max:64',
        ]);

        $activations = LicenseFeatureActivation::where('app_code', $data['app_code'])
            ->where('installation_uuid', $data['installation_uuid'])
            ->where('status', 'active')
            ->pluck('feature_key')
            ->toArray();

        return response()->json([
            'success'           => true,
            'activated_features' => $activations,
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => $message], $status);
    }
}
