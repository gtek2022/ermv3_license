<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterFeatureFlag extends Model
{
    protected $table = 'master_feature_flags';

    protected $fillable = [
        'feature_key', 'app_scope', 'enabled',
        'rollout_percentage', 'description',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'enabled'            => 'boolean',
        'rollout_percentage' => 'integer',
    ];

    public static function isEnabled(string $featureKey, string $appCode = '*'): bool
    {
        $flag = static::where('feature_key', $featureKey)
            ->where(function ($q) use ($appCode) {
                $q->where('app_scope', $appCode)
                  ->orWhere('app_scope', '*');
            })
            ->orderByRaw("CASE WHEN app_scope = ? THEN 0 ELSE 1 END", [$appCode])
            ->first();

        return $flag ? $flag->enabled : false;
    }
}
