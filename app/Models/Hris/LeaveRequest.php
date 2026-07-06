<?php

namespace App\Models\Hris;

use App\Contracts\Hris\ApprovableContract;
use App\Models\User;
use App\Services\Hris\LeaveBalanceService;
use App\Traits\Hris\Approvable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model implements ApprovableContract
{
    use Approvable;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    // Certificate tracking is deliberately separate from the status state machine
    // above: status stays approved/rejected throughout (see markAsApproved()),
    // these values only describe where the 48h medical-certificate flow is.
    public const CERT_STATUS_PENDING = 'pending';

    public const CERT_STATUS_SUBMITTED = 'submitted';

    public const CERT_STATUS_VERIFIED = 'verified';

    public const CERT_STATUS_REJECTED = 'rejected';

    public const CERT_STATUS_EXPIRED = 'expired';

    public const CERTIFICATE_DEADLINE_HOURS = 48;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'attachment_path',
        'status',
        'current_approver_id',
        'workflow_definition_id',
        'metadata',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'certificate_status',
        'certificate_deadline_at',
        'certificate_submitted_at',
        'certificate_verified_at',
        'certificate_verified_by',
        'certificate_rejection_reason',
        'auto_rejected',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'integer',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'certificate_deadline_at' => 'datetime',
        'certificate_submitted_at' => 'datetime',
        'certificate_verified_at' => 'datetime',
        'auto_rejected' => 'boolean',
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

    public function certificateVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certificate_verified_by');
    }

    /**
     * Get the module type for this approvable.
     */
    public function getApprovalModule(): string
    {
        return ApprovalWorkflow::MODULE_LEAVE;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Approved leave requests still awaiting a medical certificate, whether or
     * not their deadline has passed yet — used by both the reminder and the
     * auto-expire scheduled commands (see hris:leave-certificate-remind/-expire).
     */
    public function scopeCertificatePending($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where('certificate_status', self::CERT_STATUS_PENDING);
    }

    /**
     * Certificate-pending requests whose deadline has passed and haven't been
     * auto-rejected yet. Named to mirror TrainingRecord::scopeExpiringWithinDays().
     */
    public function scopeCertificateOverdue($query)
    {
        return $query->certificatePending()
            ->where('certificate_deadline_at', '<', now())
            ->where('auto_rejected', false);
    }

    /**
     * Certificate-pending requests whose deadline is within the given number of
     * hours (but hasn't passed yet) — for the reminder job.
     */
    public function scopeCertificateDeadlineWithinHours($query, int $hours)
    {
        return $query->certificatePending()
            ->whereBetween('certificate_deadline_at', [now(), now()->addHours($hours)]);
    }

    public function requestedPeriod(): CarbonPeriod
    {
        return CarbonPeriod::create($this->start_date, $this->end_date);
    }

    /**
     * Override markAsApproved to also consume leave balance.
     */
    public function markAsApproved(): void
    {
        // First consume the balance
        app(LeaveBalanceService::class)->consume($this);

        $approvedAt = now();

        // Then call the trait's method to update status
        $this->update([
            'status' => static::STATUS_APPROVED,
            'approved_at' => $approvedAt,
            'current_approver_id' => null,
        ]);

        // Provisional Sick Leave approval: status is already 'approved' above (unchanged
        // from today), so attendance/payroll/ESS behave exactly as before. Only start the
        // 48h certificate window when the type requires one and none was uploaded yet.
        if ($this->leaveType->requires_attachment && blank($this->attachment_path)) {
            $this->update([
                'certificate_status' => static::CERT_STATUS_PENDING,
                'certificate_deadline_at' => $approvedAt->copy()->addHours(static::CERTIFICATE_DEADLINE_HOURS),
            ]);
        }
    }
}
