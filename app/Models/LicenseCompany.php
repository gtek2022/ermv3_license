<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseCompany extends Model
{
    use SoftDeletes;

    protected $table = 'license_companies';

    protected $fillable = [
        'company_id', 'license_key', 'license_key_hash',
        'status', 'label', 'activated_at', 'expires_at',
        'max_installations', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'activated_at'     => 'datetime',
        'expires_at'       => 'datetime',
        'max_installations' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(MasterCompany::class, 'company_id');
    }

    public function licenseApps()
    {
        return $this->hasMany(LicenseApp::class, 'license_company_id');
    }

    public function installations()
    {
        return $this->hasMany(LicenseInstallation::class, 'license_company_id');
    }

    public function activeInstallations()
    {
        return $this->installations()->where('status', 'active');
    }

    public function heartbeatLogs()
    {
        return $this->hasMany(LicenseLogsHeartbeat::class, 'license_company_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Hash the license key for safe DB lookup.
     */
    public static function hashKey(string $key): string
    {
        return hash_hmac('sha256', $key, config('app.key'));
    }

    public static function findByKey(string $key): ?static
    {
        return static::where('license_key_hash', static::hashKey($key))->first();
    }
}
