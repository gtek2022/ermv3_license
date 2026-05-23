<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseAppFeature extends Model
{
    protected $table = 'license_app_features';

    protected $fillable = [
        'license_app_id', 'app_code', 'feature_key',
        'status', 'valid_until', 'created_by',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
    ];

    public function licenseApp()
    {
        return $this->belongsTo(LicenseApp::class, 'license_app_id');
    }

    public function featureMaster()
    {
        return $this->belongsTo(MasterAppFeature::class, 'feature_key', 'feature_key');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (! $this->valid_until || $this->valid_until->isFuture());
    }
}
