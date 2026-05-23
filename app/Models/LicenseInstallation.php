<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseInstallation extends Model
{
    protected $table = 'license_installations';

    protected $fillable = [
        'license_app_id', 'license_company_id', 'app_code',
        'installation_uuid', 'fingerprint', 'hostname',
        'domain', 'ip_address', 'app_version', 'status',
        'violation_counter', 'first_verified_at', 'last_heartbeat_at',
        'revoked_at', 'revoke_reason', 'meta', 'created_by',
    ];

    protected $casts = [
        'first_verified_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'revoked_at'        => 'datetime',
        'violation_counter' => 'integer',
        'meta'              => 'array',
    ];

    public function licenseCompany()
    {
        return $this->belongsTo(LicenseCompany::class, 'license_company_id');
    }

    public function licenseApp()
    {
        return $this->belongsTo(LicenseApp::class, 'license_app_id');
    }

    public function heartbeatLogs()
    {
        return $this->hasMany(LicenseLogsHeartbeat::class, 'installation_id');
    }

    public function suspiciousEvents()
    {
        return $this->hasMany(LicenseLogsSuspicious::class, 'installation_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBlacklisted(): bool
    {
        return $this->status === 'blacklisted';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
