<?php

namespace App\Services\Hris;

use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\PaymentRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PaymentRequest lifecycle + immutable COO business snapshot (EXPENSE_MODULE.MD Phase 5/6).
 */
class PaymentRequestService
{
    /** Fields whose change after COO approval voids that approval. */
    private const SENSITIVE = [
        'amount', 'currency', 'payee_name', 'bank_name', 'bank_account_number', 'source_type', 'source_id',
    ];

    /** PR statuses that no longer hold the source ER (it may be re-converted). */
    private const INACTIVE_STATUSES = [
        PaymentRequest::STATUS_REJECTED_BY_COO,
        PaymentRequest::STATUS_VOIDED,
        PaymentRequest::STATUS_CANCELLED,
        PaymentRequest::STATUS_REVERSED,
    ];

    private const VARIANCE_RISK_RATIO = 0.10;

    /** Payments at/above this require a reconciler distinct from the payer (configurable default). */
    private const HIGH_VALUE_THRESHOLD = '10000000';

    /**
     * Convert a finance-verified ExpenseRequest into a draft PaymentRequest (Phase 7).
     * Transactional + row-locked: exactly one active PR per ER, even under concurrent/double submits.
     */
    public function createFromExpenseRequest(ExpenseRequest $expenseRequest, User $creator, array $overrides = []): PaymentRequest
    {
        return DB::transaction(function () use ($expenseRequest, $creator, $overrides) {
            $er = ExpenseRequest::whereKey($expenseRequest->id)->lockForUpdate()->firstOrFail();

            if ($er->status !== ExpenseRequest::STATUS_FINANCE_VERIFIED) {
                throw new \RuntimeException('Only a finance-verified expense request can become a payment request.');
            }

            if ($er->isRecordOnly()) {
                throw new \RuntimeException('A company_paid_record_only expense request cannot create a payment request.');
            }

            $alreadyActive = PaymentRequest::where('source_type', PaymentRequest::SOURCE_EXPENSE_REQUEST)
                ->where('source_id', $er->id)
                ->whereNotIn('status', self::INACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($alreadyActive) {
                throw new \RuntimeException('This expense request already has an active payment request.');
            }

            $amount = (string) ($overrides['amount'] ?? $er->amount);
            $metadata = $this->varianceMetadata($amount, $er, $overrides);
            if ($er->finance_notes) {
                $metadata['finance_notes'] = $er->finance_notes;
            }

            $pr = PaymentRequest::create([
                'payment_request_number' => $this->generateNumber(),
                'source_type' => PaymentRequest::SOURCE_EXPENSE_REQUEST,
                'source_id' => $er->id,
                'created_by' => $creator->id,
                'payee_type' => $er->payment_mode,
                'payee_name' => $overrides['payee_name'] ?? $er->payee_name,
                'bank_name' => $overrides['bank_name'] ?? $er->payee_bank_name,
                'bank_account_number_encrypted' => $overrides['bank_account_number'] ?? $er->payee_bank_account_number,
                'bank_account_last4' => isset($overrides['bank_account_number'])
                    ? substr(preg_replace('/\D/', '', $overrides['bank_account_number']), -4)
                    : $er->payee_bank_account_last4,
                'amount' => $amount,
                'currency' => $er->currency,
                'description' => $er->title,
                'status' => PaymentRequest::STATUS_DRAFT,
                'metadata' => $metadata ?: null,
            ]);

            $er->update(['status' => ExpenseRequest::STATUS_PR_CREATED]);

            return $pr;
        });
    }

    private function varianceMetadata(string $amount, ExpenseRequest $er, array $overrides): array
    {
        if (bccomp($amount, (string) $er->amount, 2) === 0) {
            return [];
        }

        if (empty($overrides['variance_reason'])) {
            throw new \RuntimeException('A payment amount that differs from the expense request requires a justification.');
        }

        $metadata = [
            'variance_reason' => $overrides['variance_reason'],
            'er_amount' => (string) $er->amount,
        ];

        $absDiff = ltrim(bcsub($amount, (string) $er->amount, 2), '-');
        $ratio = bccomp((string) $er->amount, '0', 2) > 0
            ? (float) bcdiv($absDiff, (string) $er->amount, 6)
            : 1.0;

        if ($ratio > self::VARIANCE_RISK_RATIO) {
            $metadata['variance_risk_flag'] = true;
        }

        return $metadata;
    }

    /** When a PR no longer holds its source ER, return that ER to finance_verified (no stranding). */
    private function returnSourceExpenseRequest(PaymentRequest $pr): void
    {
        if ($pr->source_type === PaymentRequest::SOURCE_EXPENSE_REQUEST && $pr->source_id) {
            ExpenseRequest::where('id', $pr->source_id)
                ->where('status', ExpenseRequest::STATUS_PR_CREATED)
                ->update(['status' => ExpenseRequest::STATUS_FINANCE_VERIFIED]);
        }
    }

    public function createManual(User $creator, array $data): PaymentRequest
    {
        return DB::transaction(function () use ($creator, $data) {
            $attrs = $this->details($data) + [
                'payment_request_number' => $this->generateNumber(),
                'source_type' => $data['source_type'] ?? PaymentRequest::SOURCE_MANUAL,
                'source_id' => $data['source_id'] ?? null,
                'created_by' => $creator->id,
                'currency' => $data['currency'] ?? 'IDR',
                'status' => PaymentRequest::STATUS_DRAFT,
            ];
            $this->applyBank($attrs, $data);

            return PaymentRequest::create($attrs);
        });
    }

    public function submitToCoo(PaymentRequest $pr): void
    {
        if (! in_array($pr->status, [PaymentRequest::STATUS_DRAFT, PaymentRequest::STATUS_NEEDS_CLARIFICATION], true)) {
            throw new \RuntimeException('Only a draft or clarified PR can be submitted to the COO.');
        }

        $pr->update([
            'status' => PaymentRequest::STATUS_SUBMITTED_TO_COO,
            'submitted_to_coo_at' => now(),
        ]);
    }

    public function approveAsCoo(PaymentRequest $pr, User $coo): void
    {
        if ($pr->status !== PaymentRequest::STATUS_SUBMITTED_TO_COO) {
            throw new \RuntimeException('Only a PR submitted to the COO can be approved.');
        }

        DB::transaction(function () use ($pr, $coo) {
            $pr->forceFill([
                'status' => PaymentRequest::STATUS_APPROVED_BY_COO,
                'coo_approved_by' => $coo->id,
                'coo_approved_at' => now(),
            ])->save();

            $snapshot = $this->buildSnapshot($pr->fresh());
            $pr->update([
                'approval_snapshot_json' => $snapshot,
                'approval_snapshot_hash' => $this->hashSnapshot($snapshot),
            ]);

            // Lock every attachment that formed the approval basis.
            $pr->financeAttachments()->whereNull('locked_at')->update([
                'locked_at' => now(),
                'locked_reason' => 'Included in COO approval snapshot',
            ]);
        });
    }

    public function rejectAsCoo(PaymentRequest $pr, User $coo, string $reason): void
    {
        if (! in_array($pr->status, [PaymentRequest::STATUS_SUBMITTED_TO_COO, PaymentRequest::STATUS_NEEDS_CLARIFICATION], true)) {
            throw new \RuntimeException('Only a submitted PR can be rejected.');
        }

        $pr->update([
            'status' => PaymentRequest::STATUS_REJECTED_BY_COO,
            'coo_approved_by' => $coo->id,
            'coo_rejection_reason' => $reason,
        ]);

        $this->returnSourceExpenseRequest($pr);
    }

    public function requestClarification(PaymentRequest $pr, User $coo, string $note): void
    {
        if ($pr->status !== PaymentRequest::STATUS_SUBMITTED_TO_COO) {
            throw new \RuntimeException('Only a submitted PR can be sent back for clarification.');
        }

        $pr->update([
            'status' => PaymentRequest::STATUS_NEEDS_CLARIFICATION,
            'metadata' => array_merge($pr->metadata ?? [], ['clarification_note' => $note]),
        ]);
    }

    /**
     * Edit PR details. Touching a sensitive field while approved voids the approval,
     * returns the PR to draft, clears the snapshot, and requires COO re-approval.
     */
    public function updateApprovedDetails(PaymentRequest $pr, array $data): void
    {
        $attrs = $this->details($data);
        $this->applyBank($attrs, $data);

        $touchesSensitive = (bool) array_intersect(array_keys($data), self::SENSITIVE);

        if ($touchesSensitive && $pr->status === PaymentRequest::STATUS_APPROVED_BY_COO) {
            $attrs['status'] = PaymentRequest::STATUS_DRAFT;
            $attrs['approval_snapshot_json'] = null;
            $attrs['approval_snapshot_hash'] = null;
            $attrs['coo_approved_by'] = null;
            $attrs['coo_approved_at'] = null;
            $attrs['submitted_to_coo_at'] = null;
        }

        $pr->update($attrs);
    }

    public function addLine(PaymentRequest $pr, array $data): PaymentRequestLine
    {
        $attrs = array_intersect_key($data, array_flip([
            'source_line_type', 'source_line_id', 'employee_id', 'payee_name', 'bank_name', 'amount',
        ])) + [
            'payment_request_id' => $pr->id,
            'currency' => $data['currency'] ?? $pr->currency,
            'status' => PaymentRequestLine::STATUS_PENDING,
        ];

        if (array_key_exists('bank_account_number', $data)) {
            $number = $data['bank_account_number'];
            $attrs['bank_account_number_encrypted'] = $number;
            $attrs['bank_account_last4'] = $number ? substr(preg_replace('/\D/', '', $number), -4) : null;
        }

        return PaymentRequestLine::create($attrs);
    }

    public function markLinePaid(PaymentRequestLine $line, string $reference): void
    {
        $line->update([
            'status' => PaymentRequestLine::STATUS_PAID,
            'payment_reference' => $reference,
            'paid_at' => now(),
        ]);
    }

    public function markLineFailed(PaymentRequestLine $line, string $reason): void
    {
        $line->update([
            'status' => PaymentRequestLine::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Record a self-attested payment with proof → paid_pending_reconciliation (not reconciled).
     */
    public function markPaid(PaymentRequest $pr, User $executor, string $reference, FinanceAttachment $proof): void
    {
        if (! in_array($pr->status, [PaymentRequest::STATUS_APPROVED_BY_COO, PaymentRequest::STATUS_SCHEDULED_FOR_PAYMENT], true)) {
            throw new \RuntimeException('Only a COO-approved PR can be marked paid.');
        }

        DB::transaction(function () use ($pr, $executor, $reference, $proof) {
            $pr->update([
                'status' => PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION,
                'paid_by' => $executor->id,
                'payment_executor_id' => $executor->id,
                'paid_at' => now(),
                'payment_reference' => $reference,
                'payment_proof_attachment_id' => $proof->id,
            ]);

            $proof->update(['locked_at' => now(), 'locked_reason' => 'Payment proof']);
        });
    }

    /**
     * Confirm a payment against the bank statement. Payer cannot reconcile their own
     * high-value payment; any field mismatch fails reconciliation rather than closing it.
     */
    public function reconcile(PaymentRequest $pr, User $reconciler, array $observed): void
    {
        if ($pr->status !== PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION) {
            throw new \RuntimeException('Only a paid-pending-reconciliation PR can be reconciled.');
        }

        if ($this->isHighValue($pr) && (int) $pr->paid_by === (int) $reconciler->id) {
            throw new \RuntimeException('The payment executor cannot reconcile their own high-value payment.');
        }

        $matches = bccomp((string) ($observed['amount'] ?? '0'), (string) $pr->amount, 2) === 0
            && ($observed['payee_name'] ?? null) === $pr->payee_name
            && (string) ($observed['bank_account_last4'] ?? '') === (string) $pr->bank_account_last4;

        $pr->update([
            'status' => $matches ? PaymentRequest::STATUS_RECONCILED : PaymentRequest::STATUS_RECONCILIATION_FAILED,
            'reconciled_by' => $reconciler->id,
            'reconciled_at' => now(),
            'reconciliation_reference' => $observed['reference'] ?? null,
            'metadata' => $matches
                ? $pr->metadata
                : array_merge($pr->metadata ?? [], ['reconciliation_mismatch' => $observed]),
        ]);
    }

    public function void(PaymentRequest $pr, User $actor, string $reason): void
    {
        $voidable = [
            PaymentRequest::STATUS_DRAFT, PaymentRequest::STATUS_SUBMITTED_TO_COO,
            PaymentRequest::STATUS_APPROVED_BY_COO, PaymentRequest::STATUS_NEEDS_CLARIFICATION,
            PaymentRequest::STATUS_REJECTED_BY_COO,
        ];

        if (! in_array($pr->status, $voidable, true)) {
            throw new \RuntimeException('This PR can no longer be voided (it is already in a payment or terminal state).');
        }

        $pr->update([
            'status' => PaymentRequest::STATUS_VOIDED,
            'voided_by' => $actor->id,
            'voided_at' => now(),
            'metadata' => array_merge($pr->metadata ?? [], ['void_reason' => $reason]),
        ]);

        $this->returnSourceExpenseRequest($pr);
    }

    public function reverse(PaymentRequest $pr, User $actor, string $reason): void
    {
        $reversible = [
            PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION,
            PaymentRequest::STATUS_RECONCILED,
            PaymentRequest::STATUS_RECONCILIATION_FAILED,
        ];

        if (! in_array($pr->status, $reversible, true)) {
            throw new \RuntimeException('Only a paid PR can be reversed.');
        }

        $pr->update([
            'status' => PaymentRequest::STATUS_REVERSED,
            'reversed_by' => $actor->id,
            'reversed_at' => now(),
            'metadata' => array_merge($pr->metadata ?? [], ['reverse_reason' => $reason]),
        ]);
    }

    private function isHighValue(PaymentRequest $pr): bool
    {
        return bccomp((string) $pr->amount, self::HIGH_VALUE_THRESHOLD, 2) >= 0;
    }

    public function buildSnapshot(PaymentRequest $pr): array
    {
        return [
            'payment_request_number' => $pr->payment_request_number,
            'source_type' => $pr->source_type,
            'source_id' => $pr->source_id,
            'amount' => $pr->amount,
            'currency' => $pr->currency,
            'original_amount' => $pr->original_amount,
            'original_currency' => $pr->original_currency,
            'fx_rate' => $pr->fx_rate,
            'payee_name' => $pr->payee_name,
            'bank_name' => $pr->bank_name,
            'bank_account_last4' => $pr->bank_account_last4,
            'encrypted_bank_account_reference' => $pr->bank_account_number
                ? hash('sha256', $pr->bank_account_number)
                : null,
            'attachment_hashes' => $pr->financeAttachments()->pluck('file_hash')->sort()->values()->all(),
            'finance_notes' => $pr->metadata['finance_notes'] ?? null,
            'withholding_required' => (bool) $pr->withholding_required,
            'withholding_type' => $pr->withholding_type,
            'created_by' => $pr->created_by,
            'submitted_to_coo_at' => optional($pr->submitted_to_coo_at)->toIso8601String(),
            'coo_approved_by' => $pr->coo_approved_by,
            'coo_approved_at' => optional($pr->coo_approved_at)->toIso8601String(),
        ];
    }

    public function hashSnapshot(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonicalize($snapshot)));
    }

    private function canonicalize($value)
    {
        if (is_array($value)) {
            ksort($value);

            return array_map(fn ($v) => $this->canonicalize($v), $value);
        }

        return $value;
    }

    private function details(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'payee_type', 'payee_name', 'bank_name', 'amount', 'currency', 'description', 'due_date',
            'original_amount', 'original_currency', 'fx_rate',
            'withholding_required', 'withholding_type', 'withholding_amount', 'tax_notes',
        ]));
    }

    private function applyBank(array &$attrs, array $data): void
    {
        if (! array_key_exists('bank_account_number', $data)) {
            return;
        }

        $number = $data['bank_account_number'];
        $attrs['bank_account_number_encrypted'] = $number;
        $attrs['bank_account_last4'] = $number ? substr(preg_replace('/\D/', '', $number), -4) : null;
    }

    private function generateNumber(): string
    {
        $prefix = 'PR-'.now()->format('Ym').'-';

        $last = PaymentRequest::where('payment_request_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('payment_request_number')
            ->value('payment_request_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
