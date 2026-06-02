<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Final-payment authorization bundle: groups approved money-out items (PRs, weekly allowance
 * batches) into one document that is signed sequentially (finance x2 → executive) and then paid.
 * Each item carries a frozen, encrypted source snapshot so the authorized payload cannot drift.
 */
class PaymentBundle extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED_FOR_SIGNATURE = 'submitted_for_signature';

    const STATUS_PENDING_FINANCE_SIGNATURE_1 = 'pending_finance_signature_1';

    const STATUS_PENDING_FINANCE_SIGNATURE_2 = 'pending_finance_signature_2';

    const STATUS_PENDING_EXECUTIVE_SIGNATURE = 'pending_executive_signature';

    const STATUS_AUTHORIZED_FOR_PAYMENT = 'authorized_for_payment';

    const STATUS_PAYMENT_RECORDING = 'payment_recording';

    const STATUS_PAID = 'paid';

    const STATUS_PAYMENT_PROOF_REJECTED = 'payment_proof_rejected';

    const STATUS_REJECTED = 'rejected';

    const STATUS_VOIDED = 'voided';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'bundle_number', 'title', 'status', 'bundle_version',
        'period_start', 'period_end', 'total_amount', 'currency',
        'created_by', 'submitted_at', 'authorized_at',
        'paid_by', 'paid_at', 'payment_reference', 'payment_proof_attachment_id', 'payment_notes',
        'canonical_payload_json', 'canonical_payload_hash',
        'unsigned_pdf_attachment_id', 'unsigned_pdf_file_hash',
        'signed_pdf_attachment_id', 'signed_pdf_file_hash', 'signed_payload_hash',
        'public_verification_code', 'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'authorized_at' => 'datetime',
        'paid_at' => 'datetime',
        'bundle_version' => 'integer',
        'canonical_payload_json' => 'array',
        'metadata' => 'array',
    ];

    public static function signaturePendingStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED_FOR_SIGNATURE,
            self::STATUS_PENDING_FINANCE_SIGNATURE_1,
            self::STATUS_PENDING_FINANCE_SIGNATURE_2,
            self::STATUS_PENDING_EXECUTIVE_SIGNATURE,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentBundleItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->items()->whereIn('status', PaymentBundleItem::activePaymentStatuses());
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(PaymentBundleSignature::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function financeAttachments(): MorphMany
    {
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    public function unsignedPdf(): BelongsTo
    {
        return $this->belongsTo(FinanceAttachment::class, 'unsigned_pdf_attachment_id');
    }

    public function signedPdf(): BelongsTo
    {
        return $this->belongsTo(FinanceAttachment::class, 'signed_pdf_attachment_id');
    }

    public function paymentProof(): BelongsTo
    {
        return $this->belongsTo(FinanceAttachment::class, 'payment_proof_attachment_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
