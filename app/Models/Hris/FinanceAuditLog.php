<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only finance audit trail (EXPENSE_MODULE.MD Phase 10).
 * Application-level immutability: update/delete throw. Rows are hash-chained.
 */
class FinanceAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'auditable_type', 'auditable_id', 'event_type', 'actor_id', 'actor_role',
        'before_snapshot', 'after_snapshot', 'reason', 'ip_address', 'user_agent',
        'previous_hash', 'current_hash', 'created_at',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Finance audit logs are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Finance audit logs are append-only and cannot be deleted.');
        });
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
