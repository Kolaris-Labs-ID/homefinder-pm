<?php

namespace App\Services\Hris;

use App\Contracts\Hris\BatchPaymentSourceAdapter;
use App\Contracts\Hris\PayableBatchSource;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\BatchPayments\LatePayCompensationBatchPaymentSourceAdapter;
use App\Services\Hris\BatchPayments\OvertimePayBatchPaymentSourceAdapter;
use App\Services\Hris\BatchPayments\PayrunBatchPaymentSourceAdapter;
use App\Services\Hris\BatchPayments\WeeklyAllowanceBatchPaymentSourceAdapter;
use App\Support\Finance\PaymentBundleSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BatchPaymentBundleService
{
    public function __construct(private FinanceAuditLogger $audit) {}

    public function addSource(PaymentBundle $bundle, PayableBatchSource $source, User $actor): PaymentBundleItem
    {
        try {
            return DB::transaction(function () use ($bundle, $source, $actor) {
                $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

                if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                    throw new RuntimeException('Only a draft bundle can receive items.');
                }

                $source = $this->lockSource($source);
                $adapter = $this->adapterFor($source);

                if ($this->activeItemQuery($source)->exists()) {
                    throw new RuntimeException('This source is already in an active bundle.');
                }

                $adapter->assertEligible($source);

                $snapshot = $adapter->buildSnapshots($source)->primary();
                $total = $adapter->sourceTotal($source);

                if (! $total->equals($snapshot['total_amount'])) {
                    throw new RuntimeException('Source total does not match snapshot total.');
                }

                $item = PaymentBundleItem::create([
                    'payment_bundle_id' => $bundle->id,
                    'source_type' => $adapter->sourceType(),
                    'source_id' => $source->paymentSourceId(),
                    'description' => PaymentBundleSnapshot::title($snapshot),
                    'payee_name' => PaymentBundleSnapshot::firstPayeeName($snapshot),
                    'payee_bank_name' => PaymentBundleSnapshot::firstPayeeBankName($snapshot),
                    'payee_bank_account_last4' => PaymentBundleSnapshot::firstPayeeLast4($snapshot),
                    'amount' => (string) $total,
                    'currency' => 'IDR',
                    'source_snapshot_json' => $snapshot,
                    'source_hash' => $snapshot['source_hash'],
                    'status' => PaymentBundleItem::PAYMENT_STATUS_BUNDLED,
                ]);

                $adapter->markBundled($source, $bundle);
                $this->recomputeTotal($bundle);

                $this->audit->log($bundle, 'batch_source_added_to_bundle', $actor, ['after' => [
                    'item_id' => $item->id,
                    'source_type' => $item->source_type,
                    'source_id' => $item->source_id,
                    'amount' => (string) $item->amount,
                ]]);

                $this->audit->log($bundle, $this->sourceAddedEvent($adapter->sourceType()), $actor, ['after' => [
                    'item_id' => $item->id,
                    'source_id' => $item->source_id,
                ]]);

                return $item;
            });
        } catch (RuntimeException $e) {
            if ($source instanceof Model && $this->isBlockingDataMessage($e->getMessage())) {
                $source->newQuery()
                    ->whereKey($source->getKey())
                    ->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);

                // Audit the block on the bundle (created in an earlier committed transaction, so it
                // survives this rolled-back add). The reason string carries no bank/salary detail.
                $this->audit->log($bundle, 'batch_source_blocked_missing_data', $actor, ['after' => [
                    'source_type' => $source->paymentSourceType(),
                    'source_id' => $source->paymentSourceId(),
                    'blocked_reason' => $e->getMessage(),
                ]]);
            }

            throw $e;
        }
    }

    private function isBlockingDataMessage(string $message): bool
    {
        foreach (['missing payable data', 'Payroll bundling is blocked', 'does not match payable lines'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function releaseItem(PaymentBundleItem $item, PaymentBundle $bundle, ?User $actor = null): void
    {
        $source = $this->sourceForItem($item);

        if ($source !== null) {
            $this->adapterFor($source)->releaseFromBundle($source, $bundle);
        }

        $item->update(['status' => PaymentBundleItem::PAYMENT_STATUS_RELEASED]);

        $this->audit->log($bundle, 'batch_source_released_from_bundle', $actor, ['after' => [
            'item_id' => $item->id,
            'source_type' => $item->source_type,
            'source_id' => $item->source_id,
        ]]);
    }

    public function markBundleAuthorized(PaymentBundle $bundle, ?User $actor = null): void
    {
        foreach ($bundle->items()->where('status', PaymentBundleItem::PAYMENT_STATUS_BUNDLED)->get() as $item) {
            $source = $this->sourceForItem($item);
            if ($source !== null) {
                $this->adapterFor($source)->markAuthorized($source, $bundle);
            }
            $item->update(['status' => PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED]);
        }
    }

    public function markBundleRecording(PaymentBundle $bundle): void
    {
        foreach ($bundle->items()->where('status', PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED)->get() as $item) {
            $source = $this->sourceForItem($item);
            if ($source !== null) {
                $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_RECORDING]);
            }
            $item->update(['status' => PaymentBundleItem::PAYMENT_STATUS_RECORDING]);
        }
    }

    public function markItemPaid(PaymentBundleItem $item, PaymentBundle $bundle, string $idempotencyKey, ?User $actor = null): void
    {
        $source = $this->sourceForItem($item);
        $previousStatus = $source?->payment_status ?? $item->status;

        if ($source !== null) {
            $this->adapterFor($source)->markPaidFromBundle($source, $bundle, $idempotencyKey);
        }

        $item->update(['status' => PaymentBundleItem::PAYMENT_STATUS_PAID]);

        $this->audit->log($bundle, 'batch_source_marked_paid', $actor, [
            'before' => ['source_payment_status' => $previousStatus],
            'after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'source_payment_status' => PaymentBundleItem::PAYMENT_STATUS_PAID,
                'payment_proof_attachment_id' => $bundle->payment_proof_attachment_id,
                'canonical_payload_hash' => $bundle->canonical_payload_hash,
                'idempotency_key' => $idempotencyKey,
            ],
        ]);
    }

    public function assertNoSourceDrift(PaymentBundle $bundle, ?User $actor = null): void
    {
        foreach ($bundle->items()->whereIn('status', PaymentBundleItem::activePaymentStatuses())->get() as $item) {
            if (! $this->itemHasDrift($item)) {
                continue;
            }

            $this->audit->log($bundle, 'batch_source_drift_detected', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'stored_hash' => $item->source_hash,
                'live_hash' => $this->liveSourceHash($item),
            ]]);

            throw new RuntimeException('Batch source drift detected; void and recreate the bundle.');
        }
    }

    public function itemHasDrift(PaymentBundleItem $item): bool
    {
        $liveHash = $this->liveSourceHash($item);

        return $liveHash === null || ! hash_equals($item->source_hash, $liveHash);
    }

    private function liveSourceHash(PaymentBundleItem $item): ?string
    {
        if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
            $pr = PaymentRequest::find($item->source_id);

            return $pr !== null ? PaymentBundleSnapshot::hash(PaymentBundleSnapshot::forPaymentRequest($pr)) : null;
        }

        $source = $this->sourceForItem($item);

        if ($source === null) {
            return null;
        }

        return $this->adapterFor($source)->sourceHash($source);
    }

    private function activeItemQuery(PayableBatchSource $source)
    {
        return PaymentBundleItem::where('source_type', $source->paymentSourceType())
            ->where('source_id', $source->paymentSourceId())
            ->whereIn('status', PaymentBundleItem::activePaymentStatuses());
    }

    private function recomputeTotal(PaymentBundle $bundle): void
    {
        $total = '0';
        foreach ($bundle->items()->whereIn('status', PaymentBundleItem::activePaymentStatuses())->get() as $item) {
            $total = bcadd($total, (string) $item->amount, 2);
        }

        $bundle->update(['total_amount' => $total]);
    }

    private function lockSource(PayableBatchSource $source): PayableBatchSource
    {
        if (! $source instanceof Model) {
            throw new RuntimeException('Batch payment source must be an Eloquent model.');
        }

        return $source::query()->lockForUpdate()->findOrFail($source->getKey());
    }

    private function sourceForItem(PaymentBundleItem $item): ?PayableBatchSource
    {
        return match ($item->source_type) {
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH => WeeklyAllowanceBatch::find($item->source_id),
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH => OvertimePayBatch::find($item->source_id),
            PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH => LatePayCompensationBatch::find($item->source_id),
            PaymentBundleItem::SOURCE_PAYRUN_BATCH => PayrunBatch::find($item->source_id),
            default => null,
        };
    }

    private function adapterFor(PayableBatchSource $source): BatchPaymentSourceAdapter
    {
        return match (true) {
            $source instanceof WeeklyAllowanceBatch => app(WeeklyAllowanceBatchPaymentSourceAdapter::class),
            $source instanceof OvertimePayBatch => app(OvertimePayBatchPaymentSourceAdapter::class),
            $source instanceof LatePayCompensationBatch => app(LatePayCompensationBatchPaymentSourceAdapter::class),
            $source instanceof PayrunBatch => app(PayrunBatchPaymentSourceAdapter::class),
            default => throw new RuntimeException('Unsupported batch payment source.'),
        };
    }

    private function sourceAddedEvent(string $sourceType): string
    {
        return match ($sourceType) {
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH => 'weekly_allowance_added_to_bundle',
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH => 'overtime_pay_added_to_bundle',
            PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH => 'late_pay_added_to_bundle',
            PaymentBundleItem::SOURCE_PAYRUN_BATCH => 'payroll_batch_added_to_bundle',
            default => 'batch_source_added_to_bundle',
        };
    }
}
