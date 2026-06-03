<?php

/**
 * Phase 15 — ExpenseRequestResource direct-invocation authorization tests.
 *
 * MECHANISM: Filament's isDisabled() delegates to isHidden() which evaluates the
 * visible() closure server-side. mountTableAction unmounts (no-ops) if the action
 * is disabled/hidden. So an unauthorized user who directly calls mountTableAction
 * is silently blocked — the action does not execute and state is unchanged.
 *
 * These tests prove server-side enforcement by:
 * a) Showing unauthorized users cannot mutate state via direct mount invocation.
 * b) Showing authorized users CAN mutate state (proving the block is permission-specific).
 *
 * Overlap note: FinanceFilamentActionTest covers authorized success cases for verify,
 * mark_paid, approve, void. That file has NO unauthorized counterparts. This file
 * adds the direct-invocation blocked cases as net-new coverage.
 */

use App\Filament\Resources\ExpenseRequestResource\Pages\ListExpenseRequests;
use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

beforeEach(function () {
    Storage::fake('private');
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

// ─── helpers (p15er_ prefix) ────────────────────────────────────────────────

function p15er_submittedEr(): ExpenseRequest
{
    $employee = Employee::factory()->create();
    $erSvc = app(\App\Services\Hris\ExpenseRequestService::class);
    $attSvc = app(\App\Services\Hris\FinanceAttachmentService::class);

    $er = $erSvc->create($employee, [
        'category' => 'travel',
        'title' => 'P15 test taxi',
        'amount' => 150000,
        'expense_date' => '2026-06-01',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
    ]);

    $attSvc->store(
        $er,
        UploadedFile::fake()->createWithContent('rec.pdf', 'p15-receipt'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $employee->user,
    );

    $erSvc->submit($er);

    return $er->fresh();
}

// ─── [authorized] verify baseline ────────────────────────────────────────────

it('finance_reviewer verify via direct mount succeeds (authorized)', function () {
    $er = p15er_submittedEr();
    $reviewer = FinanceCenterFlowFactory::financeReviewer();

    Livewire::actingAs($reviewer)
        ->test(ListExpenseRequests::class)
        ->mountTableAction('verify', $er)
        ->set('mountedTableActionsData.0.notes', 'Receipts OK')
        ->callMountedTableAction();

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
})->group('p15-er');

// ─── [unauthorized] verify blocked ────────────────────────────────────────────

it('finance_creator (no verify perm) is blocked from verify via direct mount', function () {
    $er = p15er_submittedEr();
    // finance_creator: has view_all (can load page) but NOT expense_requests.verify
    $creator = FinanceCenterFlowFactory::financeCreator();

    Livewire::actingAs($creator)
        ->test(ListExpenseRequests::class)
        ->mountTableAction('verify', $er)
        ->set('mountedTableActionsData.0.notes', 'Bypassing hidden button')
        ->callMountedTableAction();

    // visible()=false → isHidden()=true → isDisabled()=true → action unmounts silently
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_SUBMITTED)
        ->and($er->fresh()->finance_reviewed_by)->toBeNull();
})->group('p15-er');

// ─── [authorized] reject baseline ─────────────────────────────────────────────

it('finance_reviewer reject via direct mount succeeds (authorized)', function () {
    $er = p15er_submittedEr();
    $reviewer = FinanceCenterFlowFactory::financeReviewer();

    Livewire::actingAs($reviewer)
        ->test(ListExpenseRequests::class)
        ->mountTableAction('reject', $er)
        ->set('mountedTableActionsData.0.reason', 'Not eligible')
        ->callMountedTableAction();

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_REJECTED);
})->group('p15-er');

// ─── [unauthorized] reject blocked ────────────────────────────────────────────

it('finance_creator (no reject perm) is blocked from reject via direct mount', function () {
    $er = p15er_submittedEr();
    $creator = FinanceCenterFlowFactory::financeCreator();

    Livewire::actingAs($creator)
        ->test(ListExpenseRequests::class)
        ->mountTableAction('reject', $er)
        ->set('mountedTableActionsData.0.reason', 'Unauthorized attempt')
        ->callMountedTableAction();

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_SUBMITTED)
        ->and($er->fresh()->finance_reviewed_by)->toBeNull();
})->group('p15-er');

// ─── [authorized] request_revision baseline ────────────────────────────────────

it('finance_reviewer request_revision via direct mount succeeds (authorized)', function () {
    $er = p15er_submittedEr();
    $reviewer = FinanceCenterFlowFactory::financeReviewer();

    Livewire::actingAs($reviewer)
        ->test(ListExpenseRequests::class)
        ->mountTableAction('request_revision', $er)
        ->set('mountedTableActionsData.0.note', 'Need more receipts')
        ->callMountedTableAction();

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_NEEDS_REVISION);
})->group('p15-er');

// ─── [unauthorized] request_revision blocked ────────────────────────────────────

it('finance_creator (no request_revision perm) is blocked from request_revision via direct mount', function () {
    $er = p15er_submittedEr();
    $creator = FinanceCenterFlowFactory::financeCreator();

    Livewire::actingAs($creator)
        ->test(ListExpenseRequests::class)
        ->mountTableAction('request_revision', $er)
        ->set('mountedTableActionsData.0.note', 'Unauthorized revision request')
        ->callMountedTableAction();

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_SUBMITTED)
        ->and($er->fresh()->finance_reviewed_by)->toBeNull();
})->group('p15-er');
