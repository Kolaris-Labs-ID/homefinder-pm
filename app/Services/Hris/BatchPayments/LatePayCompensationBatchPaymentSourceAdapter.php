<?php

namespace App\Services\Hris\BatchPayments;

use App\Contracts\Hris\BatchPaymentSourceAdapter;
use App\Contracts\Hris\PayableBatchSource;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Support\Finance\BatchPaymentSnapshotCollection;
use App\Support\Finance\MoneyString;
use App\Support\Finance\PaymentBundleSnapshot;
use RuntimeException;

class LatePayCompensationBatchPaymentSourceAdapter implements BatchPaymentSourceAdapter
{
    public function sourceType(): string
    {
        return PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH;
    }

    public function assertSupports(PayableBatchSource $source): void
    {
        if (! $source instanceof LatePayCompensationBatch) {
            throw new RuntimeException('Unsupported source for late-pay adapter.');
        }
    }

    public function assertEligible(PayableBatchSource $source): void
    {
        $this->assertSupports($source);

        if ($source->payment_status !== PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE) {
            throw new RuntimeException('Late-pay compensation batch is not payment eligible.');
        }

        $this->assertPayableData($source);
    }

    public function buildSnapshots(PayableBatchSource $source): BatchPaymentSnapshotCollection
    {
        $this->assertSupports($source);

        return new BatchPaymentSnapshotCollection(PaymentBundleSnapshot::forLatePayCompensationBatch($source));
    }

    public function sourceTotal(PayableBatchSource $source): MoneyString
    {
        $this->assertSupports($source);

        return MoneyString::from($source->total_amount);
    }

    public function sourceHash(PayableBatchSource $source): string
    {
        return $this->buildSnapshots($source)->primary()['source_hash'];
    }

    public function markBundled(PayableBatchSource $source, PaymentBundle $bundle): void
    {
        $this->assertSupports($source);
        $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BUNDLED]);
    }

    public function markAuthorized(PayableBatchSource $source, PaymentBundle $bundle): void
    {
        $this->assertSupports($source);
        $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED]);
    }

    public function releaseFromBundle(PayableBatchSource $source, PaymentBundle $bundle): void
    {
        $this->assertSupports($source);
        $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_RELEASED]);
    }

    public function markPaidFromBundle(PayableBatchSource $source, PaymentBundle $bundle, string $idempotencyKey): void
    {
        $this->assertSupports($source);
        $source->items()
            ->whereIn('status', [LatePayCompensationItem::STATUS_PENDING, LatePayCompensationItem::STATUS_APPROVED])
            ->update(['status' => LatePayCompensationItem::STATUS_PAID]);
        $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_PAID]);
    }

    private function assertPayableData(LatePayCompensationBatch $source): void
    {
        $items = $source->items()
            ->whereIn('status', [LatePayCompensationItem::STATUS_PENDING, LatePayCompensationItem::STATUS_APPROVED, LatePayCompensationItem::STATUS_PAID])
            ->get();

        if ($items->isEmpty()) {
            $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);
            throw new RuntimeException('Late-pay compensation batch has missing payable data.');
        }

        $total = '0';
        foreach ($items as $item) {
            if ($item->has_bank_warning || blank($item->bank_name) || blank($item->bank_account_number) || blank($item->bank_account_holder)) {
                $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);
                throw new RuntimeException('Late-pay compensation batch has missing payable data.');
            }
            $total = bcadd($total, (string) $item->total_amount, 2);
        }

        if (bccomp($total, (string) $source->total_amount, 2) !== 0) {
            $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);
            throw new RuntimeException('Late-pay compensation batch total does not match payable lines.');
        }
    }
}
