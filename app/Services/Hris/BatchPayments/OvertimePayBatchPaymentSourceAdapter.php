<?php

namespace App\Services\Hris\BatchPayments;

use App\Contracts\Hris\BatchPaymentSourceAdapter;
use App\Contracts\Hris\PayableBatchSource;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Support\Finance\BatchPaymentSnapshotCollection;
use App\Support\Finance\MoneyString;
use App\Support\Finance\PaymentBundleSnapshot;
use RuntimeException;

class OvertimePayBatchPaymentSourceAdapter implements BatchPaymentSourceAdapter
{
    public function sourceType(): string
    {
        return PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH;
    }

    public function assertSupports(PayableBatchSource $source): void
    {
        if (! $source instanceof OvertimePayBatch) {
            throw new RuntimeException('Unsupported source for overtime adapter.');
        }
    }

    public function assertEligible(PayableBatchSource $source): void
    {
        $this->assertSupports($source);

        if ($source->payment_status !== PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE) {
            throw new RuntimeException('Overtime pay batch is not payment eligible.');
        }

        $this->assertPayableData($source);
    }

    public function buildSnapshots(PayableBatchSource $source): BatchPaymentSnapshotCollection
    {
        $this->assertSupports($source);

        return new BatchPaymentSnapshotCollection(PaymentBundleSnapshot::forOvertimePayBatch($source));
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
        $source->payments()
            ->whereIn('status', [OvertimePayment::STATUS_PENDING, OvertimePayment::STATUS_APPROVED])
            ->update(['status' => OvertimePayment::STATUS_PAID]);
        $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_PAID]);
    }

    private function assertPayableData(OvertimePayBatch $source): void
    {
        $payments = $source->payments()
            ->whereIn('status', [OvertimePayment::STATUS_PENDING, OvertimePayment::STATUS_APPROVED, OvertimePayment::STATUS_PAID])
            ->get();

        if ($payments->isEmpty()) {
            $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);
            throw new RuntimeException('Overtime pay batch has missing payable data.');
        }

        $total = '0';
        foreach ($payments as $payment) {
            if ($payment->has_bank_warning || blank($payment->bank_name) || blank($payment->bank_account_number) || blank($payment->bank_account_holder)) {
                $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);
                throw new RuntimeException('Overtime pay batch has missing payable data.');
            }
            $total = bcadd($total, (string) $payment->total_amount, 2);
        }

        if (bccomp($total, (string) $source->total_amount, 2) !== 0) {
            $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);
            throw new RuntimeException('Overtime pay batch total does not match payable lines.');
        }
    }
}
