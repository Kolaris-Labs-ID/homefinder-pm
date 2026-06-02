<?php

use App\Models\Hris\Employee;
use App\Models\Hris\FinanceAttachment;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * Phase 14 — ER detail exposes a NON-sensitive linked payment status to the employee.
 */
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    $this->employee = Employee::factory()->create();
});

it('exposes the linked payment status (no sensitive bank data) on the ER detail', function () {
    $er = app(ExpenseRequestService::class)->create($this->employee, [
        'category' => 'travel', 'title' => 'Taxi', 'amount' => 1000000, 'expense_date' => '2026-05-30',
        'payment_mode' => 'vendor_payment', 'payee_name' => 'PT V', 'payee_bank_name' => 'BCA',
        'payee_bank_account_number' => '1234567890',
    ]);
    app(FinanceAttachmentService::class)->store($er, UploadedFile::fake()->createWithContent('r.pdf', 'x'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);
    app(ExpenseRequestService::class)->submit($er);
    app(ExpenseRequestService::class)->verify($er, User::factory()->create(), 'ok');
    $pr = app(PaymentRequestService::class)->createFromExpenseRequest($er->fresh(), User::factory()->create());

    Sanctum::actingAs($this->employee->user);
    $res = getJson("/api/hris/expense-requests/{$er->id}")->assertOk();

    expect($res->json('data.payment.status'))->toBe($pr->status);
    expect($res->json('data.payment.payment_request_number'))->toStartWith('PR-');
    expect($res->json('data.payment.status_label'))->toBeString();
    // No sensitive payment fields leak to the employee.
    expect($res->json('data.payment'))->not->toHaveKey('bank_account_last4');
    expect($res->json('data.payment'))->not->toHaveKey('amount');
});

it('returns null payment when the ER has no payment request yet', function () {
    $er = app(ExpenseRequestService::class)->create($this->employee, [
        'category' => 'travel', 'title' => 'Taxi', 'amount' => 1000000, 'expense_date' => '2026-05-30',
        'payment_mode' => 'employee_reimbursement',
    ]);

    Sanctum::actingAs($this->employee->user);
    expect(getJson("/api/hris/expense-requests/{$er->id}")->json('data.payment'))->toBeNull();
});
