<?php

use App\Filament\Pages\PaymentApprovalView;
use App\Filament\Resources\ExpenseRequestResource\Pages\ListExpenseRequests;
use App\Filament\Resources\PaymentRequestResource\Pages\ListPaymentRequests;
use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 5 — Filament finance action coverage (mark_paid upload, ER verify, payment approve, void).
 * Exercises the actual Livewire action + form path, not services directly.
 */
beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─── helpers (prefixed finAct to avoid collisions) ──────────────────────────

function finActMakePr(User $creator): PaymentRequest
{
    return app(PaymentRequestService::class)->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Filament Test',
        'bank_name' => 'BCA',
        'bank_account_number' => '9988776655',
        'amount' => 3000000,
        'currency' => 'IDR',
        'description' => 'Action test payment',
    ]);
}

function finActApprovedPr(User $creator, User $coo): PaymentRequest
{
    $pr = finActMakePr($creator);
    $svc = app(PaymentRequestService::class);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, $coo);

    return $pr->fresh();
}

function finActSubmittedEr(Employee $employee): ExpenseRequest
{
    $erSvc = app(ExpenseRequestService::class);
    $attSvc = app(FinanceAttachmentService::class);

    $er = $erSvc->create($employee, [
        'category' => 'travel',
        'title' => 'Filament action taxi',
        'amount' => 250000,
        'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
    ]);

    $attSvc->store(
        $er,
        UploadedFile::fake()->createWithContent('receipt.pdf', 'action-test-receipt'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $employee->user,
    );

    $erSvc->submit($er);

    return $er->fresh();
}

// ─── Test 1: mark_paid with file upload (THE KEY ONE) ───────────────────────

it('mark_paid action: uploads proof + transitions PR to paid_pending_reconciliation', function () {
    $creator = User::factory()->create();
    $coo = User::factory()->create();
    $coo->assignRole('COO');

    $executor = User::factory()->create();
    $executor->assignRole('finance_executor');

    $pr = finActApprovedPr($creator, $coo);

    $fakeProof = UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf');

    Livewire::actingAs($executor)
        ->test(ListPaymentRequests::class)
        ->callTableAction('mark_paid', $pr, data: [
            'payment_reference' => 'TRX-ACTION-001',
            'proof' => $fakeProof,
        ])
        ->assertHasNoTableActionErrors();

    $fresh = $pr->fresh();

    expect($fresh->status)->toBe(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
    expect($fresh->payment_reference)->toBe('TRX-ACTION-001');
    expect($fresh->payment_proof_attachment_id)->not->toBeNull();

    $attachment = FinanceAttachment::find($fresh->payment_proof_attachment_id);
    expect($attachment)->not->toBeNull();
    expect($attachment->attachment_role)->toBe(FinanceAttachment::ROLE_PAYMENT_PROOF);
    expect($attachment->attachable_type)->toBe(PaymentRequest::class);
    expect($attachment->attachable_id)->toBe($pr->id);

    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.uploaded')
            ->where('auditable_id', $attachment->id)
            ->exists()
    )->toBeTrue();
});

// ─── Test 2: ER verify action ────────────────────────────────────────────────

it('verify action: transitions submitted ER to finance_verified', function () {
    $employee = Employee::factory()->create();
    $er = finActSubmittedEr($employee);

    $reviewer = User::factory()->create();
    $reviewer->assignRole('finance_reviewer');

    Livewire::actingAs($reviewer)
        ->test(ListExpenseRequests::class)
        ->callTableAction('verify', $er, data: [
            'notes' => 'All receipts checked',
        ])
        ->assertHasNoTableActionErrors();

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
    expect($fresh->finance_reviewed_by)->toBe($reviewer->id);
    expect($fresh->finance_notes)->toBe('All receipts checked');
});

// ─── Test 3: Payment approval action ─────────────────────────────────────────

it('approve action on PaymentApprovalView: transitions PR to approved_by_coo with snapshot', function () {
    $creator = User::factory()->create();
    $coo = User::factory()->create();
    $coo->assignRole('COO');

    $pr = finActMakePr($creator);
    app(PaymentRequestService::class)->submitToCoo($pr);
    $pr = $pr->fresh();

    Livewire::actingAs($coo)
        ->test(PaymentApprovalView::class)
        ->callTableAction('approve', $pr)
        ->assertHasNoTableActionErrors();

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);
    expect($fresh->coo_approved_by)->toBe($coo->id);
    expect($fresh->approval_snapshot_hash)->not->toBeNull();
});

// ─── Test 4: void action ─────────────────────────────────────────────────────

it('void action: voids a PR with a reason', function () {
    $creator = User::factory()->create();
    $creator->assignRole('finance_creator');

    $pr = finActMakePr($creator);

    Livewire::actingAs($creator)
        ->test(ListPaymentRequests::class)
        ->callTableAction('void', $pr, data: [
            'reason' => 'Duplicate request submitted in error',
        ])
        ->assertHasNoTableActionErrors();

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_VOIDED);
    expect($fresh->voided_at)->not->toBeNull();
});
