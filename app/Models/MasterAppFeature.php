<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterAppFeature extends Model
{
    protected $table = 'master_app_features';

    protected $fillable = [
        'app_code', 'feature_key', 'name',
        'description', 'category', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function app()
    {
        return $this->belongsTo(MasterApp::class, 'app_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
