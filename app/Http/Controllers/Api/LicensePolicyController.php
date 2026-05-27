<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LucaLongo\Licensing\Models\License;

/**
 * Returns heartbeat policy configuration for a given license.
 *
 * ermv3 calls this endpoint so that tolerance thresholds live on the
 * server, not hard-coded in the client.
 *
 * Response shape:
 * {
 *   "success": true,
 *   "data": {
 *     "heartbeat_tolerance": 3,   // max consecutive failures before warning
 *     "warning_days": 3           // days after first failure before hard lockout
 *   }
 * }
 */
class LicensePolicyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'fingerprint'  => 'required|string|max:255',
        ]);

        $license = License::findByKey($request->input('license_key'));

        if (! $license) {
            return response()->json([
                'success' => false,
                'error'   => 'INVALID_KEY',
                'message' => 'License key is invalid or not found.',
            ], 404);
        }

        $usage = $license->usages()
            ->where('usage_fingerprint', $request->input('fingerprint'))
            ->where('status', 'active')
            ->first();

        if (! $usage) {
            return response()->json([
                'success' => false,
                'error'   => 'FINGERPRINT_MISMATCH',
                'message' => 'Fingerprint does not match an active usage for this license.',
            ], 403);
        }

        // Policy values can be overridden per-license via:
        //   1. license_companies.meta.policy (preferred — admin form di gemilang)
        //   2. package licenses.meta.policy (sync target)
        //   3. global config defaults (fallback)
        $licenseCompany = \App\Models\LicenseCompany::where('license_key_hash', $license->key_hash)->first();
        $perLicense = ($licenseCompany?->meta['policy'] ?? null) ?? ((array) $license->meta)['policy'] ?? [];

        $heartbeatTolerance = (int) ($perLicense['heartbeat_tolerance']
            ?? config('licensing-policy.heartbeat_tolerance', 3));

        $warningDays = (int) ($perLicense['warning_days']
            ?? config('licensing-policy.warning_days', 3));

        return response()->json([
            'success' => true,
            'data'    => [
                'heartbeat_tolerance' => $heartbeatTolerance,
                'warning_days'        => $warningDays,
            ],
        ]);
    }
}
