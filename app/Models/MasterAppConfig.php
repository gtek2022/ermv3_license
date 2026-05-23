<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class MasterAppConfig extends Model
{
    protected $table = 'master_app_configs';

    protected $fillable = [
        'app_code', 'config_key', 'config_value',
        'config_type', 'config_scope', 'environment',
        'description', 'is_encrypted',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public function getValue(): mixed
    {
        $raw = $this->is_encrypted
            ? Crypt::decryptString($this->config_value)
            : $this->config_value;

        return match ($this->config_type) {
            'integer' => (int) $raw,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($raw, true),
            default   => $raw,
        };
    }

    public static function getForApp(string $appCode, string $key, mixed $default = null): mixed
    {
        $config = static::where('app_code', $appCode)
            ->where('config_key', $key)
            ->first();

        return $config ? $config->getValue() : $default;
    }
}
