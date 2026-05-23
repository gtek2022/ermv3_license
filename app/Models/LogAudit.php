<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAudit extends Model
{
    public $timestamps = false;

    protected $table = 'log_audit';

    protected $fillable = [
        'user_id', 'user_name', 'action', 'module',
        'subject_type', 'subject_id', 'subject_label',
        'previous_state', 'new_state',
        'ip_address', 'user_agent', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public static function record(
        string $action,
        string $module,
        array $options = []
    ): void {
        static::create([
            'user_id'        => $options['user_id'] ?? auth()->id(),
            'user_name'      => $options['user_name'] ?? auth()->user()?->name,
            'action'         => $action,
            'module'         => $module,
            'subject_type'   => $options['subject_type'] ?? null,
            'subject_id'     => $options['subject_id'] ?? null,
            'subject_label'  => $options['subject_label'] ?? null,
            'previous_state' => isset($options['previous']) ? json_encode($options['previous']) : null,
            'new_state'      => isset($options['new']) ? json_encode($options['new']) : null,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'occurred_at'    => now(),
        ]);
    }
}
