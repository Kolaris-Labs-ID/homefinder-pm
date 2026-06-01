<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Employee-facing expense submission (EXPENSE_MODULE.MD Phase 4).
 * New money field bank account is Laravel-encrypted; only last4 is stored in clear.
 * Lifecycle is driven by ExpenseRequestService; payment belongs to PaymentRequest (no `paid` status here).
 */
class ExpenseRequest extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_UNDER_FINANCE_REVIEW = 'under_finance_review';

    const STATUS_NEEDS_REVISION = 'needs_revision';

    const STATUS_FINANCE_VERIFIED = 'finance_verified';

    const STATUS_PR_CREATED = 'pr_created';

    const STATUS_RECORD_ONLY_CLOSED = 'record_only_closed';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_CLOSED = 'closed';

    const MODE_EMPLOYEE_REIMBURSEMENT = 'employee_reimbursement';

    const MODE_VENDOR_PAYMENT = 'vendor_payment';

    const MODE_COMPANY_PAID_RECORD_ONLY = 'company_paid_record_only';

    const MODE_OTHER = 'other';

    protected $fillable = [
        'request_number', 'employee_id', 'department_id', 'project_id',
        'category', 'title', 'description', 'amount', 'currency',
        'fx_rate', 'fx_rate_source', 'fx_rate_locked_at', 'expense_date', 'payment_mode',
        'payee_name', 'payee_bank_name', 'payee_bank_account_number_encrypted', 'payee_bank_account_last4',
        'status', 'submitted_at', 'finance_reviewed_by', 'finance_reviewed_at', 'finance_notes',
        'rejection_reason', 'revision_requested_at', 'closed_at', 'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fx_rate' => 'decimal:6',
        'expense_date' => 'date',
        'fx_rate_locked_at' => 'datetime',
        'submitted_at' => 'datetime',
        'finance_reviewed_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'closed_at' => 'datetime',
        'payee_bank_account_number_encrypted' => 'encrypted',
        'metadata' => 'array',
    ];

    protected $hidden = ['payee_bank_account_number_encrypted'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function financeAttachments(): MorphMany
    {
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    /** Clear-text view of the encrypted bank account (decrypted by the cast). */
    protected function payeeBankAccountNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payee_bank_account_number_encrypted,
        );
    }

    public function hasEvidence(): bool
    {
        return $this->financeAttachments()
            ->where('attachment_role', FinanceAttachment::ROLE_EXPENSE_EVIDENCE)
            ->exists();
    }

    public function isRecordOnly(): bool
    {
        return $this->payment_mode === self::MODE_COMPANY_PAID_RECORD_ONLY;
    }

    public function getApprovalModule(): string
    {
        return ApprovalWorkflow::MODULE_EXPENSE_REQUEST;
    }
}
