<?php

namespace App\Models\Hris;

use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Reimbursement extends Model
{
    use HasFactory;

    // Type constants
    const TYPE_MEDICAL = 'medical';
    const TYPE_GLASSES = 'glasses';
    const TYPE_TRANSPORT = 'transport';
    const TYPE_OTHER = 'other';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'description',
        'receipt_file_path',
        'status',
        'rejection_reason',
        'paid_at',
        'paid_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get all available types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_MEDICAL,
            self::TYPE_GLASSES,
            self::TYPE_TRANSPORT,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get type labels for display.
     */
    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_MEDICAL => 'Medical',
            self::TYPE_GLASSES => 'Glasses/Eyewear',
            self::TYPE_TRANSPORT => 'Transport',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_PAID,
        ];
    }

    /**
     * Get status labels for display.
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PAID => 'Paid',
        ];
    }

    /**
     * Get the employee that owns this reimbursement.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who paid this reimbursement.
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get the approval steps for this reimbursement.
     */
    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable');
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending reimbursements.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved reimbursements.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Check if the reimbursement can be cancelled.
     */
    public function canCancel(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the reimbursement can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the reimbursement can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the reimbursement can be marked as paid.
     */
    public function canMarkAsPaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
