<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LicenseFeatureActivation extends Model
{
    protected $table = 'license_feature_activations';

    protected $fillable = [
        'app_code', 'feature_key', 'feature_license_key_hash',
        'installation_uuid', 'fingerprint', 'status',
        'activated_at', 'expires_at', 'revoked_at', 'created_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    public function feature()
    {
        return $this->belongsTo(MasterAppFeature::class, 'feature_key', 'feature_key');
    }

    /**
     * Has an administrator left this activation in the active state?
     *
     * Kept as it was - it answers only about `status`, and several callers mean exactly that.
     * For "may this installation use the module right now", ask isCurrentlyActive(), which also
     * weighs the deadline.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ── Validity period ───────────────────────────────────────────────────────

    /**
     * Has the term run out?
     *
     * A null deadline is perpetual, not expired - that is every activation made before FLK terms
     * existed, and getting this backwards would lock every customer out at once.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * May this installation use the feature at this moment?
     *
     * The whole rule in one place: active by status, and still inside its term. Both the catalogue
     * the client syncs and the status endpoint answer from this, so the server cannot say one thing
     * on one endpoint and something else on the other.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->isActive() && ! $this->isExpired();
    }

    /**
     * Whole days left, or null for a perpetual activation.
     *
     * Never negative: once the deadline is behind us the answer is 0, because "-3 days remaining"
     * is not something a client should have to interpret. Rounded up, so an activation with six
     * hours left reports 1 rather than 0 - a client showing "0 hari" for something still working
     * reads as broken.
     */
    public function daysRemaining(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInHours($this->expires_at) / 24);
    }

    /** Term, deadline and days left in the shape the client and the admin screens read. */
    public function validityPayload(): array
    {
        return [
            'expires_at'     => $this->expires_at?->toIso8601String(),
            'days_remaining' => $this->daysRemaining(),
            'expired'        => $this->isExpired(),
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Perpetual activations, plus dated ones whose deadline is still ahead. */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /** What the client is actually allowed to use: active by status and inside its term. */
    public function scopeLive($query)
    {
        return $query->active()->notExpired();
    }

    /**
     * Activations whose term has run out.
     *
     * Deliberately matches both 'active' and 'expired', because the sweep command flips the status
     * once the deadline passes. Filtering on 'active' alone would make this find lapsed rows only
     * until the sweep next ran, and the admin screens would then report nought expired installations
     * the moment the housekeeping caught up - the opposite of what a counter is for.
     *
     * Revoked rows are excluded: an administrator taking a licence away is not the same event as a
     * term running out, and lumping them together would hide which one happened.
     */
    public function scopeLapsed($query)
    {
        return $query->whereIn('status', ['active', 'expired'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /** Lapsed activations the sweep has not marked yet - what ExpireFeatureLicenses acts on. */
    public function scopeAwaitingExpiry($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
