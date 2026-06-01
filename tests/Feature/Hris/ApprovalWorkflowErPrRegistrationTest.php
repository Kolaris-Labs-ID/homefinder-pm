<?php

use App\Models\Hris\ApprovalWorkflow;
use App\Services\Hris\ApprovalWorkflowService;

/**
 * Phase 2 — ApprovalWorkflowService ER/PR registration (EXPENSE_MODULE.MD).
 * Registration scaffolding testable now (constants + module→class map).
 * Full "pending approvals returns ER/PR" + "URL resolves" assertions land with
 * the models (Phase 4/5) and Filament routes (Phase 11/12).
 */
it('defines ER and PR module constants', function () {
    expect(ApprovalWorkflow::MODULE_EXPENSE_REQUEST)->toBe('expense_request');
    expect(ApprovalWorkflow::MODULE_PAYMENT_REQUEST)->toBe('payment_request');
});

it('maps the ER/PR modules to their model classes', function () {
    $svc = app(ApprovalWorkflowService::class);
    expect($svc->moduleToClass(ApprovalWorkflow::MODULE_EXPENSE_REQUEST))
        ->toBe(\App\Models\Hris\ExpenseRequest::class);
    expect($svc->moduleToClass(ApprovalWorkflow::MODULE_PAYMENT_REQUEST))
        ->toBe(\App\Models\Hris\PaymentRequest::class);
});

it('still maps the legacy modules (regression guard for the extraction)', function () {
    $svc = app(ApprovalWorkflowService::class);
    expect($svc->moduleToClass(ApprovalWorkflow::MODULE_LEAVE))
        ->toBe(\App\Models\Hris\LeaveRequest::class);
    expect($svc->moduleToClass(ApprovalWorkflow::MODULE_REIMBURSEMENT))
        ->toBe(\App\Models\Hris\Reimbursement::class);
    expect($svc->moduleToClass(ApprovalWorkflow::MODULE_TRAINING))
        ->toBe(\App\Models\Hris\TrainingRecord::class);
});

it('returns null for an unknown module', function () {
    expect(app(ApprovalWorkflowService::class)->moduleToClass('not_a_module'))->toBeNull();
});

it('resolves Filament approvable URLs for ER and PR (Phase 2 deferral)', function () {
    $employee = \App\Models\Hris\Employee::factory()->create();
    $er = \App\Models\Hris\ExpenseRequest::create([
        'request_number' => 'ER-URL-1', 'employee_id' => $employee->id, 'category' => 'x', 'title' => 't',
        'amount' => 100, 'currency' => 'IDR', 'expense_date' => '2026-05-30',
        'payment_mode' => 'employee_reimbursement', 'status' => 'submitted',
    ]);
    $pr = \App\Models\Hris\PaymentRequest::create([
        'payment_request_number' => 'PR-URL-1', 'source_type' => 'manual',
        'created_by' => \App\Models\User::factory()->create()->id,
        'payee_name' => 'V', 'amount' => 100, 'currency' => 'IDR', 'status' => 'draft',
    ]);

    $svc = app(ApprovalWorkflowService::class);
    $method = new ReflectionMethod($svc, 'getApprovableUrl');
    $method->setAccessible(true);

    expect($method->invoke($svc, $er))->toContain("expense-requests/{$er->id}");
    expect($method->invoke($svc, $pr))->toContain("payment-requests/{$pr->id}");
});
