<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');

    $this->actor = User::factory()->create();
    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
});

it('marks linked batch sources and payable lines paid only when the bundle is marked paid', function () {
    $employee = Employee::factory()->create([
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
    ]);

    $batch = WeeklyAllowanceBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => WeeklyAllowanceBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => '125000.00',
    ]);

    $payment = WeeklyAllowancePayment::create([
        'weekly_allowance_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'daily_rate' => '125000.00',
        'days_present' => 1,
        'total_amount' => '125000.00',
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
        'status' => WeeklyAllowancePayment::STATUS_APPROVED,
    ]);

    $bundle = $this->bundles->create($this->actor);
    $item = $this->batchBundles->addSource($bundle, $batch, $this->actor);

    expect($payment->fresh()->status)->toBe(WeeklyAllowancePayment::STATUS_APPROVED);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);

    $this->batchBundles->markBundleAuthorized($bundle->fresh(), $this->actor);
    $bundle->forceFill([
        'status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
        'signed_pdf_attachment_id' => 999,
    ])->save();

    $paid = $this->payments->markPaid(
        $bundle->fresh(),
        $this->actor,
        UploadedFile::fake()->create('proof.pdf', 25, 'application/pdf'),
        'TRX-001',
    );

    expect($paid->status)->toBe(PaymentBundle::STATUS_PAID);
    expect($item->fresh()->status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
    expect($payment->fresh()->status)->toBe(WeeklyAllowancePayment::STATUS_PAID);
});
