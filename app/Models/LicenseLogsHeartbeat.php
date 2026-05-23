<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseLogsHeartbeat extends Model
{
    public $timestamps = false;

    protected $table = 'license_logs_heartbeats';

    protected $fillable = [
        'installation_id', 'license_company_id', 'app_code',
        'installation_uuid', 'fingerprint', 'ip_address',
        'app_version', 'domain', 'status', 'failure_reason',
        'config_version', 'violation_counter', 'response_policy',
        'heartbeat_at',
    ];

    protected $casts = [
        'heartbeat_at'    => 'datetime',
        'response_policy' => 'array',
        'violation_counter' => 'integer',
    ];

    public function installation()
    {
        return $this->belongsTo(LicenseInstallation::class, 'installation_id');
    }

    public function licenseCompany()
    {
        return $this->belongsTo(LicenseCompany::class, 'license_company_id');
    }
}
