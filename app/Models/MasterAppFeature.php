<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MasterAppFeature extends Model
{
    protected $table = 'master_app_features';

    protected $fillable = [
        'app_code', 'feature_key', 'name', 'description',
        'category', 'is_active', 'requires_license',
        'feature_license_key_hash', 'feature_license_key_encrypted',
        'created_by',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'requires_license' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function app()
    {
        return $this->belongsTo(MasterApp::class, 'app_code', 'code');
    }

    public function featureActivations()
    {
        return $this->hasMany(LicenseFeatureActivation::class, 'feature_key', 'feature_key')
            ->where('app_code', $this->app_code);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFree($query)
    {
        return $query->where('requires_license', false);
    }

    public function scopeLicensed($query)
    {
        return $query->where('requires_license', true);
    }

    // ── Feature license key management ───────────────────────────────────────

    /**
     * Generate a new feature license key and store its hash + encrypted value.
     * Returns the plain key (shown once, not stored in plain text).
     */
    public function generateFeatureLicenseKey(): string
    {
        $key = 'FLK-' . strtoupper(Str::random(4))
             . '-' . strtoupper(Str::random(4))
             . '-' . strtoupper(Str::random(4))
             . '-' . strtoupper(Str::random(4));

        $this->update([
            'feature_license_key_hash'      => $this->hashKey($key),
            'feature_license_key_encrypted' => Crypt::encryptString($key),
        ]);

        return $key;
    }

    /**
     * Retrieve the original feature license key (if APP_KEY unchanged).
     */
    public function retrieveFeatureLicenseKey(): ?string
    {
        if (! $this->feature_license_key_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->feature_license_key_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Verify a given key against the stored hash.
     */
    public function verifyFeatureLicenseKey(string $key): bool
    {
        if (! $this->feature_license_key_hash) {
            return false;
        }

        return hash_equals($this->feature_license_key_hash, $this->hashKey($key));
    }

    public static function hashKey(string $key): string
    {
        return hash_hmac('sha256', $key, config('app.key'));
    }

    /**
     * Find a feature by its license key hash.
     */
    public static function findByLicenseKey(string $key): ?static
    {
        return static::where('feature_license_key_hash', static::hashKey($key))->first();
    }
}
