<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shared protected-file layer for ER evidence, PR payment proof, bank statements,
 * and reconciliation files (EXPENSE_MODULE.MD Phase 3). Private disk only; every
 * download is authorized; every file is sha256-hashed; locked files are immutable.
 */
class FinanceAttachment extends Model
{
    use HasFactory;

    const ROLE_EXPENSE_EVIDENCE = 'expense_evidence';

    const ROLE_PAYMENT_PROOF = 'payment_proof';

    const ROLE_BANK_STATEMENT = 'bank_statement';

    const ROLE_RECONCILIATION_EVIDENCE = 'reconciliation_evidence';

    const ROLE_SUPPORTING_DOCUMENT = 'supporting_document';

    const ROLE_PAYMENT_BUNDLE_UNSIGNED_PDF = 'payment_bundle_unsigned_pdf';

    const ROLE_PAYMENT_BUNDLE_SIGNED_PDF = 'payment_bundle_signed_pdf';

    const ROLE_PAYMENT_BUNDLE_SIGNATURE_IMAGE = 'payment_bundle_signature_image';

    protected $fillable = [
        'attachable_type', 'attachable_id', 'attachment_role',
        'file_path', 'file_hash', 'file_size', 'mime_type', 'original_filename',
        'uploaded_by', 'uploaded_at', 'locked_at', 'locked_reason', 'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
        'locked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function hasDuplicateWarning(): bool
    {
        return (bool) ($this->metadata['duplicate_warning'] ?? false);
    }
}
