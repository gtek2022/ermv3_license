<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterConfigVersion extends Model
{
    public $timestamps = false;

    protected $table = 'master_config_versions';

    protected $fillable = [
        'config_type', 'config_id', 'config_key',
        'previous_value', 'new_value',
        'changed_by', 'change_reason', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function snapshot(
        string $configType,
        int $configId,
        string $configKey,
        ?string $previousValue,
        ?string $newValue,
        ?string $reason = null
    ): void {
        static::create([
            'config_type'    => $configType,
            'config_id'      => $configId,
            'config_key'     => $configKey,
            'previous_value' => $previousValue,
            'new_value'      => $newValue,
            'changed_by'     => auth()->id(),
            'change_reason'  => $reason,
            'created_at'     => now(),
        ]);
    }
}
