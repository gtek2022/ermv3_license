<?php

namespace App\Services\Licensing;

use App\Models\LicenseSigningKey;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Wraps libsodium Ed25519 signing for license server responses.
 *
 * Design intent:
 * - Server holds the private key. Client only ever sees the public key embedded in code.
 * - Every payload sent to client is signed; client verifies signature before trusting it.
 * - Without the private key, an attacker cannot fabricate a valid response,
 *   even if they fully control the network and the client's environment variables.
 */
class SigningService
{
    /**
     * Generate a new Ed25519 keypair and persist it.
     */
    public function generateAndStoreKeypair(?string $kid = null): LicenseSigningKey
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('PHP sodium extension is required for license signing.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $kid = $kid ?: substr(hash('sha256', $publicKey), 0, 16);

        // Deactivate previous active keys
        LicenseSigningKey::query()->where('is_active', true)->update([
            'is_active' => false,
            'rotated_at' => now(),
        ]);

        return LicenseSigningKey::create([
            'kid' => $kid,
            'algorithm' => 'ed25519',
            'public_key' => base64_encode($publicKey),
            'private_key_encrypted' => Crypt::encryptString($secretKey),
            'is_active' => true,
        ]);
    }

    /**
     * Sign a canonical JSON payload, returning a structured envelope:
     *
     * {
     *   "kid":  "<key id>",
     *   "alg":  "ed25519",
     *   "data": "<base64url payload>",
     *   "sig":  "<base64url signature>"
     * }
     */
    public function signPayload(array $payload, ?LicenseSigningKey $key = null): array
    {
        $key = $key ?: LicenseSigningKey::active();
        if (! $key) {
            throw new RuntimeException('No active license signing key. Run: php artisan license:keys:generate');
        }

        $canonical = $this->canonicalize($payload);
        $signature = sodium_crypto_sign_detached($canonical, $key->privateKey());

        return [
            'kid' => $key->kid,
            'alg' => $key->algorithm,
            'data' => $this->base64UrlEncode($canonical),
            'sig' => $this->base64UrlEncode($signature),
        ];
    }

    /**
     * Verify a signed envelope using the active public key (used in tests
     * and by self-check inside the server itself).
     */
    public function verifyEnvelope(array $envelope): bool
    {
        $key = LicenseSigningKey::query()->where('kid', $envelope['kid'] ?? '')->first();
        if (! $key) {
            return false;
        }

        $data = $this->base64UrlDecode($envelope['data'] ?? '');
        $sig = $this->base64UrlDecode($envelope['sig'] ?? '');

        if ($data === '' || $sig === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $data, $key->publicKeyRaw());
    }

    public function canonicalize(array $payload): string
    {
        ksort($payload);

        // Recursively sort nested arrays for deterministic output.
        array_walk_recursive($payload, function (&$v): void {
            // No-op, just to walk.
        });

        return json_encode($this->deepSort($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function deepSort(array $payload): array
    {
        ksort($payload);
        foreach ($payload as $k => $v) {
            if (is_array($v)) {
                $payload[$k] = $this->deepSort($v);
            }
        }

        return $payload;
    }

    public function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $text): string
    {
        $remainder = strlen($text) % 4;
        if ($remainder !== 0) {
            $text .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($text, '-_', '+/')) ?: '';
    }
}
