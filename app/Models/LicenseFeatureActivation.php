<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseFeatureActivation extends Model
{
    protected $table = 'license_feature_activations';

    protected $fillable = [
        'app_code', 'feature_key', 'feature_license_key_hash',
        'installation_uuid', 'fingerprint', 'status',
        'activated_at', 'revoked_at', 'created_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    public function feature()
    {
        return $this->belongsTo(MasterAppFeature::class, 'feature_key', 'feature_key');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
