<?php

use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PayrunBatch;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundleService;

it('blocks payroll bundling until frozen payroll payee bank data and tax guards exist', function () {
    $actor = User::factory()->create();
    $bundle = app(PaymentBundleService::class)->create($actor);

    $payrun = PayrunBatch::create([
        'period_start_date' => '2026-05-01',
        'period_end_date' => '2026-05-31',
        'pay_date' => '2026-06-02',
        'status' => PayrunBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
    ]);

    expect(fn () => app(BatchPaymentBundleService::class)->addSource($bundle, $payrun, $actor))
        ->toThrow(RuntimeException::class, 'Payroll bundling is blocked');

    expect($payrun->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BLOCKED);
});
