<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterApp extends Model
{
    use SoftDeletes;

    protected $table = 'master_apps';

    protected $fillable = [
        'code', 'name', 'description',
        'version', 'base_url', 'status', 'icon',
        'created_by', 'updated_by',
    ];

    public function features()
    {
        return $this->hasMany(MasterAppFeature::class, 'app_code', 'code');
    }

    public function activeFeatures()
    {
        return $this->features()->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
