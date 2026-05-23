<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseNonce extends Model
{
    public $timestamps = false;

    protected $table = 'license_nonces';

    protected $fillable = [
        'nonce', 'installation_uuid', 'used_at', 'expires_at',
    ];

    protected $casts = [
        'used_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Check if a nonce has been used (replay protection).
     */
    public static function isUsed(string $nonce): bool
    {
        return static::where('nonce', $nonce)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Consume a nonce — store it so it cannot be reused.
     */
    public static function consume(string $nonce, ?string $installationUuid = null): void
    {
        static::create([
            'nonce'             => $nonce,
            'installation_uuid' => $installationUuid,
            'used_at'           => now(),
            'expires_at'        => now()->addMinutes(5),
        ]);
    }

    /**
     * Purge expired nonces (run via scheduler).
     */
    public static function purgeExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
