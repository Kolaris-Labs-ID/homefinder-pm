<?php

namespace App\Services\Hris\BatchPayments;

use App\Contracts\Hris\BatchPaymentSourceAdapter;
use App\Contracts\Hris\PayableBatchSource;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PayrunBatch;
use App\Support\Finance\BatchPaymentSnapshotCollection;
use App\Support\Finance\MoneyString;
use RuntimeException;

class PayrunBatchPaymentSourceAdapter implements BatchPaymentSourceAdapter
{
    public function sourceType(): string
    {
        return PaymentBundleItem::SOURCE_PAYRUN_BATCH;
    }

    public function assertSupports(PayableBatchSource $source): void
    {
        if (! $source instanceof PayrunBatch) {
            throw new RuntimeException('Unsupported source for payroll adapter.');
        }
    }

    public function assertEligible(PayableBatchSource $source): void
    {
        $this->assertSupports($source);
        $source->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_BLOCKED]);

        throw new RuntimeException('Payroll bundling is blocked until payroll frozen payee bank data and year-end tax guards are implemented.');
    }

    public function buildSnapshots(PayableBatchSource $source): BatchPaymentSnapshotCollection
    {
        $this->assertEligible($source);
    }

    public function sourceTotal(PayableBatchSource $source): MoneyString
    {
        return MoneyString::from('0.00');
    }

    public function sourceHash(PayableBatchSource $source): string
    {
        $this->assertEligible($source);
    }

    public function markBundled(PayableBatchSource $source, PaymentBundle $bundle): void {}

    public function markAuthorized(PayableBatchSource $source, PaymentBundle $bundle): void {}

    public function releaseFromBundle(PayableBatchSource $source, PaymentBundle $bundle): void {}

    public function markPaidFromBundle(PayableBatchSource $source, PaymentBundle $bundle, string $idempotencyKey): void {}
}
