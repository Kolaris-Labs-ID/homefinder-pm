<?php

use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentRequestService;

/**
 * Phase 10 — append-only finance audit log with hash chain (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    $this->logger = app(FinanceAuditLogger::class);
    $this->service = app(PaymentRequestService::class);
    $this->actor = User::factory()->create();
});

it('writes an append-only entry and chains the hash to the previous one', function () {
    $pr = $this->service->createManual($this->actor, ['payee_name' => 'V', 'amount' => 100, 'currency' => 'IDR']);

    $first = $this->logger->log($pr, 'test.one', $this->actor);
    $second = $this->logger->log($pr, 'test.two', $this->actor);

    expect($first->current_hash)->not->toBeNull();
    expect($second->previous_hash)->toBe($first->current_hash);
    expect($second->current_hash)->not->toBe($first->current_hash);
});

it('forbids updating an audit entry', function () {
    $pr = $this->service->createManual($this->actor, ['payee_name' => 'V', 'amount' => 100, 'currency' => 'IDR']);
    $log = $this->logger->log($pr, 'test.one', $this->actor);

    expect(fn () => $log->update(['event_type' => 'tampered']))->toThrow(RuntimeException::class);
});

it('forbids deleting an audit entry', function () {
    $pr = $this->service->createManual($this->actor, ['payee_name' => 'V', 'amount' => 100, 'currency' => 'IDR']);
    $log = $this->logger->log($pr, 'test.one', $this->actor);

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});

it('audits a COO approval and a void through the service', function () {
    $coo = User::factory()->create();
    $pr = $this->service->createManual($this->actor, ['payee_name' => 'V', 'amount' => 100, 'currency' => 'IDR']);
    $this->service->submitToCoo($pr);
    $this->service->approveAsCoo($pr, $coo);

    expect(FinanceAuditLog::where('auditable_type', PaymentRequest::class)
        ->where('auditable_id', $pr->id)
        ->where('event_type', 'payment_request.approved_by_coo')
        ->exists())->toBeTrue();
});
