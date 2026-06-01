<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->svc = app(ExpenseRequestService::class);
    $this->attachSvc = app(FinanceAttachmentService::class);
    $this->employee = Employee::factory()->create();
    $this->finance = User::factory()->create();
});

function erAuditMakeDraft(array $overrides = []): ExpenseRequest
{
    return test()->svc->create(test()->employee, array_merge([
        'category' => 'travel',
        'title' => 'Taxi',
        'amount' => 150000,
        'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
    ], $overrides));
}

function erAuditAttachEvidence(ExpenseRequest $er): void
{
    test()->attachSvc->store(
        $er,
        UploadedFile::fake()->createWithContent('r.pdf', 'x'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        test()->employee->user,
    );
}

function erAuditHasLog(ExpenseRequest $er, string $eventType): bool
{
    return FinanceAuditLog::where('auditable_type', ExpenseRequest::class)
        ->where('auditable_id', $er->id)
        ->where('event_type', $eventType)
        ->exists();
}

it('logs expense_request.draft_created on create', function () {
    $er = erAuditMakeDraft();

    expect(erAuditHasLog($er, 'expense_request.draft_created'))->toBeTrue();
});

it('logs expense_request.submitted on submit', function () {
    $er = erAuditMakeDraft();
    erAuditAttachEvidence($er);
    $this->svc->submit($er);

    expect(erAuditHasLog($er, 'expense_request.submitted'))->toBeTrue();
});

it('logs expense_request.finance_verified on verify', function () {
    $er = erAuditMakeDraft();
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->verify($er, $this->finance, 'ok');

    expect(erAuditHasLog($er, 'expense_request.finance_verified'))->toBeTrue();
});

it('logs expense_request.rejected on reject with reason stored', function () {
    $er = erAuditMakeDraft();
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->reject($er, $this->finance, 'duplicate claim');

    expect(erAuditHasLog($er, 'expense_request.rejected'))->toBeTrue();

    $log = FinanceAuditLog::where('auditable_type', ExpenseRequest::class)
        ->where('auditable_id', $er->id)
        ->where('event_type', 'expense_request.rejected')
        ->first();
    expect($log->reason)->toBe('duplicate claim');
});

it('logs expense_request.revision_requested on requestRevision with note stored', function () {
    $er = erAuditMakeDraft();
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->requestRevision($er, $this->finance, 'attach itemised receipt');

    expect(erAuditHasLog($er, 'expense_request.revision_requested'))->toBeTrue();

    $log = FinanceAuditLog::where('auditable_type', ExpenseRequest::class)
        ->where('auditable_id', $er->id)
        ->where('event_type', 'expense_request.revision_requested')
        ->first();
    expect($log->reason)->toBe('attach itemised receipt');
});

it('logs expense_request.revised on revise', function () {
    $er = erAuditMakeDraft();
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->requestRevision($er, $this->finance, 'fix amount');
    $this->svc->revise($er, ['amount' => 200000]);

    expect(erAuditHasLog($er, 'expense_request.revised'))->toBeTrue();
});

it('logs expense_request.record_only_closed on closeAsRecordOnly', function () {
    $er = erAuditMakeDraft(['payment_mode' => ExpenseRequest::MODE_COMPANY_PAID_RECORD_ONLY]);
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->verify($er, $this->finance, 'recorded');
    $this->svc->closeAsRecordOnly($er);

    expect(erAuditHasLog($er, 'expense_request.record_only_closed'))->toBeTrue();
});

it('logs expense_request.cancelled on cancel', function () {
    $er = erAuditMakeDraft();
    $this->svc->cancel($er);

    expect(erAuditHasLog($er, 'expense_request.cancelled'))->toBeTrue();
});

it('logs expense_request.converted_to_pr on ER→PR conversion', function () {
    $er = erAuditMakeDraft([
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
        'payee_name' => 'PT Vendor',
        'payee_bank_name' => 'BCA',
        'payee_bank_account_number' => '1234567890',
    ]);
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->verify($er, $this->finance, 'ok');

    $creator = User::factory()->create();
    $pr = app(PaymentRequestService::class)->createFromExpenseRequest($er->fresh(), $creator);

    expect(erAuditHasLog($er, 'expense_request.converted_to_pr'))->toBeTrue();

    $log = FinanceAuditLog::where('auditable_type', ExpenseRequest::class)
        ->where('auditable_id', $er->id)
        ->where('event_type', 'expense_request.converted_to_pr')
        ->first();
    expect($log->after_snapshot)->toHaveKey('payment_request_id', $pr->id);
});

it('actor on create is the employee user', function () {
    $er = erAuditMakeDraft();

    $log = FinanceAuditLog::where('auditable_type', ExpenseRequest::class)
        ->where('auditable_id', $er->id)
        ->where('event_type', 'expense_request.draft_created')
        ->first();
    expect($log->actor_id)->toBe($this->employee->user->id);
});

it('actor on finance_verified is the finance user', function () {
    $er = erAuditMakeDraft();
    erAuditAttachEvidence($er);
    $this->svc->submit($er);
    $this->svc->verify($er, $this->finance, 'ok');

    $log = FinanceAuditLog::where('auditable_type', ExpenseRequest::class)
        ->where('auditable_id', $er->id)
        ->where('event_type', 'expense_request.finance_verified')
        ->first();
    expect($log->actor_id)->toBe($this->finance->id);
});
