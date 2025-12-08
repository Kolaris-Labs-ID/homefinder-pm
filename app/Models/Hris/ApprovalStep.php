<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalStep extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'workflow_id',
        'approvable_type',
        'approvable_id',
        'step_order',
        'approver_id',
        'approver_role',
        'status',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * Get the approvable model (LeaveRequest, etc.).
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the approver user.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Get the workflow this step belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }
}
