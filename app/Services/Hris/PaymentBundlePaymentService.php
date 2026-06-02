<?php

namespace App\Services\Hris;

use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Support\Finance\PaymentBundleSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Payment execution from the frozen snapshot. Once a bundle is authorized + signed, finance pays
 * each item manually using the values frozen at assembly time. This service is snapshot-authoritative:
 * payment details are read only from PaymentBundleItem::source_snapshot_json, never from live sources.
 * Drift blocks payment-recording and mark-paid entry so finance must void/recreate a changed bundle.
 */
class PaymentBundlePaymentService
{
    public function __construct(
        protected FinanceAuditLogger $audit,
        protected FinanceAttachmentService $attachments,
        protected BatchPaymentBundleService $batchSources,
    ) {}

    /**
     * Internal frozen instructions builder. The returned bank_account_number is the full account
     * stored in the encrypted snapshot. UI/API callers that expose these values must use
     * revealPaymentInstructions() so the reveal is audited and permission-gated at the caller.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentInstructions(PaymentBundle $bundle): array
    {
        $items = $bundle->items()
            ->whereIn('status', PaymentBundleItem::activePaymentStatuses())
            ->get();

        return $items->flatMap(function (PaymentBundleItem $item) {
            $snap = $item->source_snapshot_json;

            if (PaymentBundleSnapshot::isVersionedBatchSnapshot($snap)) {
                return collect($snap['payees'])->map(function (array $payee) use ($item, $snap) {
                    $protected = collect($snap['protected_payees'] ?? [])
                        ->firstWhere('payee_id', $payee['payee_id']);

                    return [
                        'item_id' => $item->id,
                        'source_type' => $item->source_type,
                        'payee_name' => $payee['payee_name'],
                        'bank_name' => $payee['bank_name'],
                        'bank_account_number' => $protected['bank_account_number'] ?? null,
                        'amount' => $payee['amount'],
                        'currency' => $payee['currency'],
                        'description' => $snap['summary']['label'] ?? null,
                        'drift' => $this->detectDrift($item),
                    ];
                });
            }

            return [[
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'payee_name' => $snap['payee_name'],
                'bank_name' => $snap['bank_name'] ?? null,
                'bank_account_number' => $snap['bank_account_number'] ?? null,
                'amount' => $snap['amount'],
                'currency' => $snap['currency'],
                'description' => $snap['description'] ?? null,
                'drift' => $this->detectDrift($item),
            ]];
        })->all();
    }

    /**
     * Actor-aware full-bank reveal path for manual payment execution. This deliberately audits
     * metadata only; full account numbers stay out of the audit log.
     *
     * @return array<int, array<string, mixed>>
     */
    public function revealPaymentInstructions(PaymentBundle $bundle, User $actor): array
    {
        $instructions = $this->paymentInstructions($bundle);

        $this->audit->log($bundle, 'batch_bank_detail_revealed', $actor, ['after' => [
            'instruction_count' => count($instructions),
            'item_ids' => collect($instructions)->pluck('item_id')->unique()->values()->all(),
        ]]);

        return $instructions;
    }

    /**
     * Rebuild the snapshot from the CURRENT live source and compare its hash to the frozen
     * source_hash. Returns true if they differ (the live source changed since authorization) or if
     * the live source no longer exists. Pure read — mutates nothing.
     */
    public function detectDrift(PaymentBundleItem $item): bool
    {
        $live = $this->liveSnapshot($item);

        if ($live === null) {
            return true;
        }

        return PaymentBundleSnapshot::hash($live) !== $item->source_hash;
    }

    /**
     * Move an authorized + signed bundle into payment_recording and hand finance the frozen
     * instructions to pay manually. Drift blocks the transition so payment instructions cannot be
     * revealed from a changed source.
     *
     * @return array<int, array<string, mixed>>
     */
    public function beginPaymentRecording(PaymentBundle $bundle, User $actor): array
    {
        if ($bundle->status === PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT) {
            $this->batchSources->assertNoSourceDrift($bundle, $actor);
        }

        return DB::transaction(function () use ($bundle, $actor) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT) {
                throw new \RuntimeException('Only an authorized bundle can enter payment recording.');
            }

            if ($bundle->signed_pdf_attachment_id === null) {
                throw new \RuntimeException('A signed authorization document is required before payment.');
            }

            $activeItems = $bundle->items()->whereIn('status', PaymentBundleItem::activePaymentStatuses())->get();

            $oldStatus = $bundle->status;
            $bundle->update(['status' => PaymentBundle::STATUS_PAYMENT_RECORDING]);

            $this->batchSources->markBundleRecording($bundle->fresh());

            foreach ($activeItems->where('source_type', PaymentBundleItem::SOURCE_PAYMENT_REQUEST) as $item) {
                $item->update(['status' => PaymentBundleItem::STATUS_PAYMENT_RECORDING]);
            }

            $this->audit->log($bundle, 'payment_bundle.payment_recording_started', $actor, [
                'before' => ['status' => $oldStatus],
                'after' => ['status' => PaymentBundle::STATUS_PAYMENT_RECORDING],
            ]);

            return $this->revealPaymentInstructions($bundle->refresh(), $actor);
        });
    }

    /**
     * Upload payment proof, mark the bundle paid, cascade paid status to items and live sources.
     * Accepts bundles in authorized_for_payment, payment_recording, or payment_proof_rejected.
     * High-value bundles (≥ threshold) enforce segregation of duties: the actor (payer) must not
     * be a finance signer on the same bundle.
     */
    public function markPaid(
        PaymentBundle $bundle,
        User $actor,
        UploadedFile $proof,
        ?string $reference = null,
        ?string $notes = null,
    ): PaymentBundle {
        if (in_array($bundle->status, [
            PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
            PaymentBundle::STATUS_PAYMENT_RECORDING,
            PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED,
        ], true)) {
            $this->batchSources->assertNoSourceDrift($bundle, $actor);
        }

        return DB::transaction(function () use ($bundle, $actor, $proof, $reference, $notes) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            $allowedStatuses = [
                PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
                PaymentBundle::STATUS_PAYMENT_RECORDING,
                PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED,
            ];

            if (! in_array($bundle->status, $allowedStatuses, true)) {
                throw new \RuntimeException('Only an authorized/recording/proof-rejected bundle can be marked paid.');
            }

            if ($bundle->signed_pdf_attachment_id === null) {
                throw new \RuntimeException('A signed authorization document is required.');
            }

            // High-value SoD: payer must not be a finance signer on this bundle.
            if (bccomp((string) $bundle->total_amount, (string) config('finance.payment_bundle_high_value_threshold'), 2) >= 0) {
                $financeSignerIds = $bundle->signatures()
                    ->where('status', \App\Models\Hris\PaymentBundleSignature::STATUS_SIGNED)
                    ->get()
                    ->filter(fn ($sig) => $sig->signature_slot->isFinance()) // signature_slot is cast to the enum
                    ->pluck('signed_by')
                    ->filter()
                    ->all();

                if (in_array($actor->id, $financeSignerIds, true)) {
                    throw new \RuntimeException('Segregation of duties: the payer cannot be a finance signer on a high-value bundle.');
                }
            }

            $attachment = $this->attachments->store($bundle, $proof, FinanceAttachment::ROLE_PAYMENT_PROOF, $actor);

            $this->audit->log($bundle, 'payment_bundle.payment_proof_uploaded', $actor, [
                'after' => [
                    'attachment_id' => $attachment->id,
                    'file_hash' => $attachment->file_hash,
                ],
            ]);

            $oldStatus = $bundle->status;
            $paidAt = now();
            $bundle->update([
                'status' => PaymentBundle::STATUS_PAID,
                'paid_by' => $actor->id,
                'paid_at' => $paidAt,
                'payment_reference' => $reference,
                'payment_notes' => $notes,
                'payment_proof_attachment_id' => $attachment->id,
            ]);

            // Cascade paid status to items.
            $itemStatuses = PaymentBundleItem::activePaymentStatuses();
            $items = $bundle->items()->whereIn('status', $itemStatuses)->get();

            foreach ($items as $item) {
                $this->markSourcePaid($item, $bundle, $actor);
            }

            $this->audit->log($bundle, 'payment_bundle.marked_paid', $actor, [
                'before' => ['status' => $oldStatus],
                'after' => [
                    'status' => PaymentBundle::STATUS_PAID,
                    'paid_by' => $actor->id,
                    'paid_at' => $paidAt->toIso8601String(),
                    'payment_reference' => $reference,
                    'total_amount' => (string) $bundle->total_amount,
                ],
            ]);

            return $bundle->refresh();
        });
    }

    /**
     * Reject the payment proof on a paid bundle (e.g. wrong amount shown, blurry scan).
     * The existing proof attachment is kept for the audit trail. Finance re-uploads via markPaid.
     */
    public function rejectPaymentProof(PaymentBundle $bundle, User $actor, string $reason): PaymentBundle
    {
        return DB::transaction(function () use ($bundle, $actor, $reason) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_PAID) {
                throw new \RuntimeException('Only a paid bundle can have its payment proof rejected.');
            }

            $meta = $bundle->metadata ?? [];
            $meta['payment_proof_rejection_reason'] = $reason;
            $meta['payment_proof_rejected_by'] = $actor->id;
            $meta['payment_proof_rejected_at'] = now()->toIso8601String();

            $oldStatus = $bundle->status;
            $bundle->update([
                'status' => PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED,
                'metadata' => $meta,
            ]);

            $this->audit->log($bundle, 'payment_bundle.payment_proof_rejected', $actor, [
                'reason' => $reason,
                'before' => ['status' => $oldStatus],
                'after' => ['status' => PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED],
            ]);

            return $bundle->refresh();
        });
    }

    /** Mark the live source record paid (best-effort; source may have been deleted). */
    private function markSourcePaid(PaymentBundleItem $item, PaymentBundle $bundle, User $actor): void
    {
        if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
            $item->update(['status' => PaymentBundleItem::STATUS_PAID]);
            PaymentRequest::where('id', $item->source_id)->update(['status' => PaymentRequest::STATUS_PAID]);
        } elseif (in_array($item->source_type, [
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH,
            PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH,
            PaymentBundleItem::SOURCE_PAYRUN_BATCH,
        ], true)) {
            $this->batchSources->markItemPaid($item, $bundle, 'payment-bundle:'.$bundle->id, $actor);
        }
    }

    /** The live source rebuilt as a snapshot array, or null if the source row no longer exists. */
    private function liveSnapshot(PaymentBundleItem $item): ?array
    {
        if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
            $pr = PaymentRequest::find($item->source_id);

            return $pr !== null ? PaymentBundleSnapshot::forPaymentRequest($pr) : null;
        }

        if ($item->source_type === PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH) {
            $wab = WeeklyAllowanceBatch::find($item->source_id);

            return $wab !== null ? PaymentBundleSnapshot::forWeeklyAllowanceBatch($wab) : null;
        }

        if ($item->source_type === PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH) {
            $batch = OvertimePayBatch::find($item->source_id);

            return $batch !== null ? PaymentBundleSnapshot::forOvertimePayBatch($batch) : null;
        }

        if ($item->source_type === PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH) {
            $batch = LatePayCompensationBatch::find($item->source_id);

            return $batch !== null ? PaymentBundleSnapshot::forLatePayCompensationBatch($batch) : null;
        }

        return null;
    }
}
