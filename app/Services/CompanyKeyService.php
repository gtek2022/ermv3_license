<?php

namespace App\Services;

use LucaLongo\Licensing\Models\LicensingKey;

/**
 * Provides the global public key info for client app configuration.
 *
 * The package uses ONE global signing key for all PASETO tokens.
 * All client apps (regardless of company or app type) use the same
 * LICENSING_PUBLIC_KEY — it only proves the token came from this Gemilang server.
 *
 * Security per-company/per-app is handled by:
 *   - license_key (LIC-XXXX) — unique per license bundle
 *   - fingerprint — bound to specific machine
 *   - app_code — which app is licensed
 */
class CompanyKeyService
{
    /**
     * Get the global public key info — same for all companies and apps.
     *
     * @param  string|null  $appCode  Optional — used to generate app-specific snippet label
     * @param  string|null  $companyName  Optional — used for snippet comment
     */
    public function getPublicKeyInfo(?string $appCode = null, ?string $companyName = null): array
    {
        $signingKey = LicensingKey::findActiveSigning();

        if (! $signingKey) {
            return [
                'has_key' => false,
                'message' => 'Belum ada signing key aktif. Jalankan: php artisan licensing:keys:generate',
            ];
        }

        $serverUrl = rtrim(config('app.url'), '/');
        $issuer    = config('licensing.offline_token.issuer', 'laravel-licensing');

        $snippetLines = [];
        if ($companyName) {
            $snippetLines[] = "# {$companyName}" . ($appCode ? " — {$appCode}" : '');
        }
        $snippetLines[] = "LICENSING_SERVER_URL={$serverUrl}";
        $snippetLines[] = 'LICENSING_PUBLIC_KEY="' . $signingKey->getPublicKey() . '"';
        $snippetLines[] = "LICENSING_ISSUER={$issuer}";
        $snippetLines[] = "# Kunci lisensi (LIC-XXXX) dimasukkan via /license/install setelah deploy";

        return [
            'has_key'    => true,
            'kid'        => $signingKey->kid,
            'public_key' => $signingKey->getPublicKey(),
            'algorithm'  => $signingKey->algorithm ?? 'Ed25519',
            'valid_from' => $signingKey->valid_from?->format('d M Y H:i'),
            'valid_until'=> $signingKey->valid_until?->format('d M Y H:i') ?? '∞',
            'status'     => $signingKey->status->value,
            'server_url' => $serverUrl,
            'issuer'     => $issuer,
            'note'       => '1 public key untuk semua company & semua aplikasi. Keamanan per-company dijamin oleh license_key (LIC-XXXX) dan fingerprint, bukan public key.',
            'env_snippet'=> implode("\n", $snippetLines),
        ];
    }
}
