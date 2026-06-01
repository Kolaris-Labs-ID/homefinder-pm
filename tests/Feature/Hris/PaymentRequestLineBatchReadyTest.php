<?php

use App\Models\Hris\PaymentRequest;
use App\Models\Hris\PaymentRequestLine;
use App\Models\User;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8 — PaymentRequestLine batch-ready design (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    $this->service = app(PaymentRequestService::class);
    $this->pr = $this->service->createManual(User::factory()->create(), [
        'payee_type' => 'batch', 'payee_name' => 'Payroll', 'bank_name' => 'BCA',
        'amount' => 0, 'currency' => 'IDR',
    ]);
});

it('adds a line to a PR, defaulting to pending', function () {
    $line = $this->service->addLine($this->pr, [
        'payee_name' => 'Alice', 'bank_name' => 'BCA', 'bank_account_number' => '1111222233', 'amount' => 1000000,
    ]);

    expect($line->status)->toBe(PaymentRequestLine::STATUS_PENDING);
    expect($line->payment_request_id)->toBe($this->pr->id);
    expect($line->amount)->toBe('1000000.00');
});

it('supports many lines with independent payment status (partial failure)', function () {
    $a = $this->service->addLine($this->pr, ['payee_name' => 'A', 'amount' => 100, 'bank_account_number' => '111']);
    $b = $this->service->addLine($this->pr, ['payee_name' => 'B', 'amount' => 200, 'bank_account_number' => '222']);
    $c = $this->service->addLine($this->pr, ['payee_name' => 'C', 'amount' => 300, 'bank_account_number' => '333']);

    $this->service->markLinePaid($a, 'TRX-A');
    $this->service->markLineFailed($b, 'account closed');

    expect($this->pr->lines()->count())->toBe(3);
    expect($a->fresh()->status)->toBe(PaymentRequestLine::STATUS_PAID);
    expect($a->fresh()->payment_reference)->toBe('TRX-A');
    expect($b->fresh()->status)->toBe(PaymentRequestLine::STATUS_FAILED);
    expect($b->fresh()->failure_reason)->toBe('account closed');
    expect($c->fresh()->status)->toBe(PaymentRequestLine::STATUS_PENDING);
});

it('encrypts a line bank account and stores last4', function () {
    $line = $this->service->addLine($this->pr, [
        'payee_name' => 'Alice', 'bank_account_number' => '9876543210', 'amount' => 500000,
    ]);

    expect($line->bank_account_last4)->toBe('3210');
    expect($line->bank_account_number)->toBe('9876543210');
    $raw = DB::table('payment_request_lines')->where('id', $line->id)->value('bank_account_number_encrypted');
    expect($raw)->not->toBe('9876543210');
});
