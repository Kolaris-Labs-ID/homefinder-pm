<?php

namespace App\Contracts\Hris;

use App\Models\Hris\PaymentBundle;
use App\Support\Finance\BatchPaymentSnapshotCollection;
use App\Support\Finance\MoneyString;

interface BatchPaymentSourceAdapter
{
    public function sourceType(): string;

    public function assertSupports(PayableBatchSource $source): void;

    public function assertEligible(PayableBatchSource $source): void;

    public function buildSnapshots(PayableBatchSource $source): BatchPaymentSnapshotCollection;

    public function sourceTotal(PayableBatchSource $source): MoneyString;

    public function sourceHash(PayableBatchSource $source): string;

    public function markBundled(PayableBatchSource $source, PaymentBundle $bundle): void;

    public function markAuthorized(PayableBatchSource $source, PaymentBundle $bundle): void;

    public function releaseFromBundle(PayableBatchSource $source, PaymentBundle $bundle): void;

    public function markPaidFromBundle(PayableBatchSource $source, PaymentBundle $bundle, string $idempotencyKey): void;
}
