<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseApp extends Model
{
    protected $table = 'license_apps';

    protected $fillable = [
        'license_company_id', 'app_code', 'status',
        'valid_from', 'valid_until', 'max_installations',
        'notes', 'created_by',
    ];

    protected $casts = [
        'valid_from'        => 'datetime',
        'valid_until'       => 'datetime',
        'max_installations' => 'integer',
    ];

    public function licenseCompany()
    {
        return $this->belongsTo(LicenseCompany::class, 'license_company_id');
    }

    public function features()
    {
        return $this->hasMany(LicenseAppFeature::class, 'license_app_id');
    }

    public function activeFeatures()
    {
        return $this->features()->where('status', 'active');
    }

    public function installations()
    {
        return $this->hasMany(LicenseInstallation::class, 'license_app_id');
    }

    public function app()
    {
        return $this->belongsTo(MasterApp::class, 'app_code', 'code');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
