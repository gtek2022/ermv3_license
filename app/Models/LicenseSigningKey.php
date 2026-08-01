<?php

namespace App\Models;

use LucaLongo\Licensing\Enums\KeyStatus;
use LucaLongo\Licensing\Enums\KeyType;
use LucaLongo\Licensing\Models\LicensingKey;

/**
 * The signing key SigningService asks for.
 *
 * This class was referenced by App\Services\Licensing\SigningService and did not exist, so every call
 * to signPayload() threw "Class not found" - which ConfigSyncController catches and reports as
 * SIGNING_FAILED. The visible effect was that /api/platform/v1/config-sync answered 500 for every
 * client, so no installation could ever learn its feature catalogue or its entitlements. That is why
 * feature licensing looked like it was not implemented.
 *
 * Rather than introduce a second key table, this is the package's own licensing_keys - the same rows
 * that sign the PASETO tokens - narrowed to the signing keys and given the accessor names
 * SigningService uses. One set of keys, one place they are rotated.
 */
class LicenseSigningKey extends LicensingKey
{
    /**
     * The newest usable signing key.
     *
     * Expiry is preferred, not required. The package gives a signing key a 30-day valid_until and the
     * ones on this server are past it, while the licences they signed are still in service - so
     * insisting on an unexpired key here would keep config-sync broken for a reason that has nothing
     * to do with the caller. Rotating the signing keys is a separate operation with its own blast
     * radius (every client that has cached a public key), and it is not this class's decision.
     */
    public static function active(): ?self
    {
        return static::query()
            ->where('type', KeyType::Signing)
            ->where('status', KeyStatus::Active)
            ->orderByRaw('CASE WHEN valid_until IS NULL OR valid_until > NOW() THEN 0 ELSE 1 END')
            ->orderByDesc('valid_from')
            ->first();
    }

    /** Raw Ed25519 secret key bytes, as sodium_crypto_sign_detached() wants them. */
    public function privateKey(): string
    {
        $stored = $this->getPrivateKey();

        if (! $stored) {
            throw new \RuntimeException('Signing key ' . $this->kid . ' has no private key on record.');
        }

        // The package stores base64 of the raw key inside its encrypted envelope.
        $decoded = base64_decode($stored, true);

        return $decoded !== false ? $decoded : $stored;
    }

    /** Raw Ed25519 public key bytes, for verifying an envelope this server produced. */
    public function publicKeyRaw(): string
    {
        $decoded = base64_decode($this->public_key, true);

        return $decoded !== false ? $decoded : $this->public_key;
    }

    /** SigningService writes 'ed25519' in the envelope; the package stores 'Ed25519'. */
    public function getAlgorithmAttribute($value): string
    {
        return strtolower((string) $value);
    }

    /**
     * No global scope narrowing this to signing keys, deliberately.
     *
     * The parent declares its own booted() with a creating hook that fills in the kid, and a child
     * booted() replaces it rather than adding to it - so a scope added here would have quietly cost
     * every key row its identifier. The two queries in this class filter by type themselves.
     */
}
