<?php

namespace App\Services\Licensing;

use LucaLongo\Licensing\Enums\KeyStatus;
use LucaLongo\Licensing\Enums\KeyType;
use LucaLongo\Licensing\Models\LicensingKey;
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
    public function generateAndStoreKeypair(?string $kid = null): LicensingKey
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('PHP sodium extension is required for license signing.');
        }

        /*
         * The package's own key store, not a second one.
         *
         * This wrote to a table of its own invention - is_active and rotated_at, which licensing_keys
         * does not have - through a model class that was never written. Nothing called it, so the
         * mistake stayed hidden; signPayload() below did call that missing class, and that is what made
         * config-sync answer 500 for every client.
         *
         * generateKeyPair() encrypts the private key with the package's passphrase scheme, so a key
         * issued here is usable by the token signer too.
         */
        $key = new LicensingKey();

        if ($kid) {
            $key->kid = $kid;
        }

        return $key->generateKeyPair(KeyType::Signing);
    }

    /**
     * The newest usable signing key.
     *
     * Unexpired is preferred, not required. The package gives a signing key a 30-day valid_until, and
     * the ones on this server are past it while the licences they signed are still in service -
     * insisting here would keep config-sync broken for a reason that has nothing to do with the
     * caller. Rotating the keys touches every client that has cached a public key, so that is a
     * separate decision and deliberately not made here.
     */
    public function activeSigningKey(): ?LicensingKey
    {
        return LicensingKey::query()
            ->where('type', KeyType::Signing)
            ->where('status', KeyStatus::Active)
            ->orderByRaw('CASE WHEN valid_until IS NULL OR valid_until > NOW() THEN 0 ELSE 1 END')
            ->orderByDesc('valid_from')
            ->first();
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
    public function signPayload(array $payload, ?LicensingKey $key = null): array
    {
        $key = $key ?: $this->activeSigningKey();
        if (! $key) {
            throw new RuntimeException('No active license signing key.');
        }

        $canonical = $this->canonicalize($payload);
        $signature = sodium_crypto_sign_detached($canonical, $this->rawPrivateKey($key));

        return [
            'kid' => $key->kid,
            'alg' => strtolower((string) $key->algorithm),
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
        $key = LicensingKey::query()->where('kid', $envelope['kid'] ?? '')->first();
        if (! $key) {
            return false;
        }

        $data = $this->base64UrlDecode($envelope['data'] ?? '');
        $sig = $this->base64UrlDecode($envelope['sig'] ?? '');

        if ($data === '' || $sig === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $data, $this->rawPublicKey($key));
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

    /**
     * The package keeps both keys base64 encoded inside its own envelopes; sodium wants raw bytes.
     */
    private function rawPrivateKey(LicensingKey $key): string
    {
        $stored = $key->getPrivateKey();

        if (! $stored) {
            throw new RuntimeException('Signing key ' . $key->kid . ' has no private key on record.');
        }

        $decoded = base64_decode($stored, true);

        return $decoded !== false ? $decoded : $stored;
    }

    private function rawPublicKey(LicensingKey $key): string
    {
        $decoded = base64_decode((string) $key->public_key, true);

        return $decoded !== false ? $decoded : (string) $key->public_key;
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
