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
        'license_duration_days', 'license_duration_minutes',
        'feature_license_key_hash', 'feature_license_key_encrypted',
        'created_by',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'requires_license'         => 'boolean',
        'license_duration_days'    => 'integer',
        'license_duration_minutes' => 'integer',
    ];

    /**
     * Minutes in the units an admin actually thinks in, smallest first.
     *
     * Used by the admin form's unit selector and by validation, so the set of accepted units is
     * declared once rather than repeated in each form and each controller.
     */
    public const DURATION_UNITS = [
        'minutes' => 1,
        'hours'   => 60,
        'days'    => 1440,
    ];

    /**
     * Ten years, the previous ceiling of 3650 days expressed in minutes.
     *
     * Beyond this a term is practically perpetual and the number is almost always a typo.
     */
    public const MAX_DURATION_MINUTES = 3650 * 1440;

    /**
     * Convert an amount and a unit into minutes, or null for perpetual.
     *
     * The single place the pair is combined, so the admin create form, the inline edit and any
     * future caller cannot disagree about what "2 hours" means.
     */
    public static function minutesFrom(?int $amount, ?string $unit): ?int
    {
        if ($amount === null || $amount <= 0) {
            return null;
        }

        $multiplier = self::DURATION_UNITS[$unit] ?? self::DURATION_UNITS['days'];

        return $amount * $multiplier;
    }

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

    // ── Validity period ───────────────────────────────────────────────────────

    /**
     * Does redeeming this FLK grant access for a limited term?
     *
     * False means lifetime, which is what an FLK was before terms existed and what every key issued
     * until now still is.
     */
    public function hasValidityPeriod(): bool
    {
        return $this->durationMinutes() !== null;
    }

    /**
     * The term in minutes, or null for perpetual. The one read path for a duration.
     *
     * Falls back to the day column for any row written before minute granularity existed and not
     * yet converted. The migration converts them all, so this is a belt-and-braces read for a row
     * that arrived by some other route - a restore from an older dump, most plausibly - rather than
     * a second source of truth. Everything else in the application asks this method.
     */
    public function durationMinutes(): ?int
    {
        $minutes = $this->license_duration_minutes;

        if ($minutes !== null && $minutes > 0) {
            return $minutes;
        }

        $days = $this->license_duration_days;

        return ($days !== null && $days > 0) ? $days * 1440 : null;
    }

    /**
     * Store a term, keeping the legacy day column as a derived mirror.
     *
     * The mirror is never read by this code. It is written so that rolling back to a build that only
     * knows about days cannot read NULL and conclude "perpetual", which would turn a short trial
     * into a free permanent licence. Rounded up and floored at one day, so the rollback reading is
     * always a real term rather than zero.
     */
    public function setDurationMinutes(?int $minutes): void
    {
        $minutes = ($minutes !== null && $minutes > 0) ? $minutes : null;

        $this->update([
            'license_duration_minutes' => $minutes,
            'license_duration_days'    => $minutes === null ? null : max(1, (int) ceil($minutes / 1440)),
        ]);
    }

    /**
     * Is this a lifetime FLK - once redeemed, never lapses?
     *
     * A null duration is the single record of this. There is deliberately no separate
     * `is_lifetime` flag: two columns answering one question is how they end up disagreeing, and
     * there would be no honest answer for a row claiming lifetime and thirty days at once. The admin
     * screens ask the question as an explicit choice and store the answer here.
     */
    public function isLifetime(): bool
    {
        return ! $this->hasValidityPeriod();
    }

    /** 'lifetime' or 'term' - what the admin form's radio binds to. */
    public function validityMode(): string
    {
        return $this->isLifetime() ? 'lifetime' : 'term';
    }

    /**
     * When an activation redeemed right now would lapse, or null for a perpetual key.
     *
     * The one place the term is turned into a deadline, so activation and renewal cannot drift
     * apart on how it is counted.
     */
    public function expiryFromNow(): ?\Illuminate\Support\Carbon
    {
        $minutes = $this->durationMinutes();

        return $minutes !== null ? now()->addMinutes($minutes) : null;
    }

    /** "15 menit", "6 jam", "30 hari" or "Lifetime", for the admin screens and flash messages. */
    public function validityLabel(): string
    {
        $minutes = $this->durationMinutes();

        return $minutes !== null ? self::humaniseMinutes($minutes) : 'Lifetime';
    }

    /**
     * The amount and unit to show in an edit form, chosen as the largest unit that divides exactly.
     *
     * A term stored as 43200 minutes was almost certainly entered as 30 days, and showing it back as
     * 43200 minutes would be technically correct and useless. Anything that does not divide cleanly
     * stays in minutes, because rounding the number in an editable field would quietly change the
     * term the next time the form was saved.
     *
     * @return array{amount: int|null, unit: string}
     */
    public function durationForForm(): array
    {
        $minutes = $this->durationMinutes();

        if ($minutes === null) {
            return ['amount' => null, 'unit' => 'days'];
        }

        foreach (['days' => 1440, 'hours' => 60] as $unit => $size) {
            if ($minutes % $size === 0) {
                return ['amount' => intdiv($minutes, $size), 'unit' => $unit];
            }
        }

        return ['amount' => $minutes, 'unit' => 'minutes'];
    }

    /**
     * Minutes as the shortest phrase that says the same thing: "45 menit", "6 jam", "30 hari".
     *
     * Mixed remainders are spelled out rather than rounded ("1 hari 6 jam"), because a term is a
     * commercial fact and "about a day" is not a useful thing to show next to a licence key.
     */
    public static function humaniseMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 menit';
        }

        $days  = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins  = $minutes % 60;

        $parts = [];
        if ($days > 0)  { $parts[] = $days . ' hari'; }
        if ($hours > 0) { $parts[] = $hours . ' jam'; }
        if ($mins > 0)  { $parts[] = $mins . ' menit'; }

        return implode(' ', $parts);
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
