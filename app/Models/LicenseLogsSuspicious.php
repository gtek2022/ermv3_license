<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseLogsSuspicious extends Model
{
    public $timestamps = false;

    protected $table = 'license_logs_suspicious';

    protected $fillable = [
        'installation_id', 'license_company_id', 'app_code',
        'installation_uuid', 'event_type',
        'registered_fingerprint', 'received_fingerprint',
        'ip_address', 'domain', 'details', 'severity',
        'is_reviewed', 'reviewed_by', 'reviewed_at', 'occurred_at',
    ];

    protected $casts = [
        'is_reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    public function installation()
    {
        return $this->belongsTo(LicenseInstallation::class, 'installation_id');
    }

    public function scopeUnreviewed($query)
    {
        return $query->where('is_reviewed', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }
}
