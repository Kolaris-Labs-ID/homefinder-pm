<?php

namespace App\Models\Hris;

use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeaveRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'current_approver_id',
        'workflow_definition_id',
        'metadata',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'integer',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    /**
     * Get the approval steps for this leave request.
     * This is a polymorphic relationship to ApprovalStep.
     */
    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step_order');
    }

    /**
     * Get the current pending approval step.
     */
    public function currentApprovalStep(): ?ApprovalStep
    {
        return $this->approvalSteps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->first();
    }

    /**
     * Check if this leave request has approval workflow steps.
     */
    public function hasApprovalWorkflow(): bool
    {
        return $this->approvalSteps()->exists();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function requestedPeriod(): CarbonPeriod
    {
        return CarbonPeriod::create($this->start_date, $this->end_date);
    }

    /**
     * Mark the leave request as approved.
     * Called by ApprovalWorkflowService when final step is approved.
     */
    public function markAsApproved(): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark the leave request as rejected.
     * Called by ApprovalWorkflowService when a step is rejected.
     */
    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}

