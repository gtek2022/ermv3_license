<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use LucaLongo\Licensing\Models\LicensingKey;

/**
 * Returns the active global public key for client app configuration.
 *
 * The package uses ONE global signing key for all tokens.
 * All client apps (regardless of company) use the same LICENSING_PUBLIC_KEY.
 *
 * This endpoint lets admins copy the public key from the UI
 * without running artisan commands.
 */
class PublicKeyController extends Controller
{
    public function show(): JsonResponse
    {
        $signingKey = LicensingKey::findActiveSigning();
        $rootKey    = LicensingKey::findActiveRoot();

        if (! $signingKey) {
            return response()->json([
                'success' => false,
                'message' => 'No active signing key found. Run: php artisan licensing:keys:generate',
            ], 404);
        }

        $serverUrl = rtrim(config('app.url'), '/');
        $issuer    = config('licensing.offline_token.issuer', 'laravel-licensing');

        return response()->json([
            'success'     => true,
            'signing_key' => [
                'kid'        => $signingKey->kid,
                'public_key' => $signingKey->getPublicKey(),
                'algorithm'  => $signingKey->algorithm ?? 'Ed25519',
                'valid_from' => $signingKey->valid_from?->format('d M Y H:i'),
                'valid_until'=> $signingKey->valid_until?->format('d M Y H:i') ?? '∞',
                'status'     => $signingKey->status->value,
            ],
            'root_key' => $rootKey ? [
                'kid'        => $rootKey->kid,
                'public_key' => $rootKey->getPublicKey(),
                'valid_from' => $rootKey->valid_from?->format('d M Y H:i'),
                'valid_until'=> $rootKey->valid_until?->format('d M Y H:i') ?? '∞',
            ] : null,
            'server_url'   => $serverUrl,
            'issuer'       => $issuer,
            'note'         => 'Satu public key digunakan untuk semua company dan semua aplikasi klien.',
            'env_snippet'  => implode("\n", [
                "LICENSING_SERVER_URL={$serverUrl}",
                'LICENSING_PUBLIC_KEY="' . $signingKey->getPublicKey() . '"',
                "LICENSING_ISSUER={$issuer}",
            ]),
        ]);
    }
}
