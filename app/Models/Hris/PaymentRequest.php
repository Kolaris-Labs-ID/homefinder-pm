<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Finance-controlled money-out approval (EXPENSE_MODULE.MD Phase 5/6).
 * Bank account is Laravel-encrypted (+last4 in clear). COO approval mints an
 * immutable business snapshot (approval_snapshot_json/_hash); any sensitive change
 * voids approval and forces re-approval.
 */
class PaymentRequest extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED_TO_COO = 'submitted_to_coo';

    const STATUS_APPROVED_BY_COO = 'approved_by_coo';

    const STATUS_REJECTED_BY_COO = 'rejected_by_coo';

    const STATUS_NEEDS_CLARIFICATION = 'needs_clarification';

    const STATUS_SCHEDULED_FOR_PAYMENT = 'scheduled_for_payment';

    const STATUS_PAID_PENDING_RECONCILIATION = 'paid_pending_reconciliation';

    const STATUS_RECONCILED = 'reconciled';

    const STATUS_RECONCILIATION_FAILED = 'reconciliation_failed';

    const STATUS_PAYMENT_FAILED = 'payment_failed';

    const STATUS_VOIDED = 'voided';

    const STATUS_REVERSED = 'reversed';

    const STATUS_CANCELLED = 'cancelled';

    const SOURCE_EXPENSE_REQUEST = 'expense_request';

    const SOURCE_PAYROLL_BATCH = 'payroll_batch';

    const SOURCE_WEEKLY_ALLOWANCE_BATCH = 'weekly_allowance_batch';

    const SOURCE_LATE_PAY_COMPENSATION = 'late_pay_compensation';

    const SOURCE_OVERTIME_PAY = 'overtime_pay';

    const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'payment_request_number', 'source_type', 'source_id',
        'created_by', 'reviewed_by', 'payment_executor_id',
        'payee_type', 'payee_name', 'bank_name', 'bank_account_number_encrypted', 'bank_account_last4',
        'amount', 'currency', 'original_amount', 'original_currency', 'fx_rate',
        'description', 'due_date', 'status',
        'approval_snapshot_json', 'approval_snapshot_hash',
        'submitted_to_coo_at', 'coo_approved_by', 'coo_approved_at', 'coo_rejection_reason',
        'scheduled_for_payment_at', 'paid_by', 'paid_at', 'payment_reference', 'payment_proof_attachment_id',
        'reconciled_by', 'reconciled_at', 'reconciliation_reference', 'bank_statement_attachment_id',
        'voided_by', 'voided_at', 'reversed_by', 'reversed_at',
        'withholding_required', 'withholding_type', 'withholding_amount', 'tax_notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'fx_rate' => 'decimal:6',
        'due_date' => 'date',
        'submitted_to_coo_at' => 'datetime',
        'coo_approved_at' => 'datetime',
        'scheduled_for_payment_at' => 'datetime',
        'paid_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'voided_at' => 'datetime',
        'reversed_at' => 'datetime',
        'withholding_required' => 'boolean',
        'bank_account_number_encrypted' => 'encrypted',
        'approval_snapshot_json' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = ['bank_account_number_encrypted'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cooApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coo_approved_by');
    }

    public function paymentExecutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_executor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentRequestLine::class);
    }

    public function financeAttachments(): MorphMany
    {
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    public function expenseRequest(): ?ExpenseRequest
    {
        return $this->source_type === self::SOURCE_EXPENSE_REQUEST && $this->source_id
            ? ExpenseRequest::find($this->source_id)
            : null;
    }

    /** Clear-text view of the encrypted bank account (decrypted by the cast). */
    protected function bankAccountNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bank_account_number_encrypted,
        );
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED_BY_COO;
    }

    public function getApprovalModule(): string
    {
        return ApprovalWorkflow::MODULE_PAYMENT_REQUEST;
    }
}
