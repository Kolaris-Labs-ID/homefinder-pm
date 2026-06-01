<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payee line of a PaymentRequest (EXPENSE_MODULE.MD Phase 8).
 * ER PR = one header + optional one line; batch PR = one header + many lines,
 * each with independent payment status (partial failure representable).
 */
class PaymentRequestLine extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PAID = 'paid';

    const STATUS_FAILED = 'failed';

    const STATUS_RECONCILED = 'reconciled';

    protected $fillable = [
        'payment_request_id', 'source_line_type', 'source_line_id', 'employee_id',
        'payee_name', 'bank_name', 'bank_account_number_encrypted', 'bank_account_last4',
        'amount', 'currency', 'status', 'payment_reference', 'paid_at', 'failure_reason', 'reconciled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'bank_account_number_encrypted' => 'encrypted',
    ];

    protected $hidden = ['bank_account_number_encrypted'];

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function bankAccountNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bank_account_number_encrypted,
        );
    }
}
