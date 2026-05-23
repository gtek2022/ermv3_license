<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class MasterConfig extends Model
{
    protected $table = 'master_configs';

    protected $fillable = [
        'config_key', 'config_value', 'config_type',
        'category', 'description', 'is_encrypted',
        'is_public', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_public'    => 'boolean',
    ];

    /**
     * Get the decrypted value.
     */
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

    /**
     * Set and optionally encrypt the value.
     */
    public function setValue(mixed $value): void
    {
        $raw = is_array($value) ? json_encode($value) : (string) $value;

        $this->config_value = $this->is_encrypted
            ? Crypt::encryptString($raw)
            : $raw;
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Get a config value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $config = static::where('config_key', $key)->first();

        return $config ? $config->getValue() : $default;
    }
}
