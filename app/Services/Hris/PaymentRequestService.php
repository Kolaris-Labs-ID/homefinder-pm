<?php

namespace App\Services\Hris;

use App\Models\Hris\PaymentRequest;
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
