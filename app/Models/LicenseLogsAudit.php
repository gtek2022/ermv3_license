<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseLogsAudit extends Model
{
    public $timestamps = false;

    protected $table = 'license_logs_audit';

    protected $fillable = [
        'event_type', 'subject_type', 'subject_id',
        'actor_id', 'actor_name', 'ip_address',
        'previous_state', 'new_state', 'reason', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * Record a license audit event.
     */
    public static function record(
        string $eventType,
        string $subjectType,
        int $subjectId,
        array $options = []
    ): void {
        static::create([
            'event_type'     => $eventType,
            'subject_type'   => $subjectType,
            'subject_id'     => $subjectId,
            'actor_id'       => $options['actor_id'] ?? auth()->id(),
            'actor_name'     => $options['actor_name'] ?? auth()->user()?->name,
            'ip_address'     => $options['ip'] ?? request()->ip(),
            'previous_state' => isset($options['previous']) ? json_encode($options['previous']) : null,
            'new_state'      => isset($options['new']) ? json_encode($options['new']) : null,
            'reason'         => $options['reason'] ?? null,
            'occurred_at'    => now(),
        ]);
    }
}
