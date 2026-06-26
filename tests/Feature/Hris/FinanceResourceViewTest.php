<?php

use App\Filament\Resources\ExpenseRequestResource;
use App\Filament\Resources\ExpenseRequestResource\Pages\ViewExpenseRequest;
use App\Filament\Resources\PaymentRequestResource;
use App\Filament\Resources\PaymentRequestResource\Pages\ViewPaymentRequest;
use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\FinanceAttachmentService;
use Filament\Infolists\Infolist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Phase 12 — Smoke tests for ER/PR Filament infolists.
 * Verifies infolist() returns a non-empty schema and that ViewRecord pages render OK.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin->assignRole($role);
    }
});

// --- infolist() schema registration ---

it('ExpenseRequestResource::infolist returns an Infolist with schema', function () {
    $infolist = app(Infolist::class);
    $result = ExpenseRequestResource::infolist($infolist);

    expect($result)->toBeInstanceOf(Infolist::class)
        ->and($result->getComponents())->not->toBeEmpty();
});

it('PaymentRequestResource::infolist returns an Infolist with schema', function () {
    $infolist = app(Infolist::class);
    $result = PaymentRequestResource::infolist($infolist);

    expect($result)->toBeInstanceOf(Infolist::class)
        ->and($result->getComponents())->not->toBeEmpty();
});

// --- ViewRecord page render smoke tests ---

it('ViewExpenseRequest page renders for a basic ER', function () {
    $employee = Employee::factory()->create();
    $er = ExpenseRequest::create([
        'request_number' => 'ER-VIEW-'.uniqid(),
        'employee_id' => $employee->id,
        'category' => 'travel',
        'title' => 'Taxi to client',
        'amount' => 120000,
        'currency' => 'IDR',
        'expense_date' => '2026-05-01',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
        'status' => ExpenseRequest::STATUS_SUBMITTED,
    ]);

    Livewire::actingAs($this->admin)
        ->test(ViewExpenseRequest::class, ['record' => $er->getRouteKey()])
        ->assertOk();
});

it('ViewExpenseRequest page includes an evidence photo preview URL', function () {
    Storage::fake('private');
    $employee = Employee::factory()->create();
    $er = ExpenseRequest::create([
        'request_number' => 'ER-VIEW-'.uniqid(),
        'employee_id' => $employee->id,
        'category' => 'travel',
        'title' => 'Taxi to client',
        'amount' => 120000,
        'currency' => 'IDR',
        'expense_date' => '2026-05-01',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
        'status' => ExpenseRequest::STATUS_SUBMITTED,
    ]);
    $attachment = app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->create('receipt.jpg', 4, 'image/jpeg'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $employee->user,
    );

    Livewire::actingAs($this->admin)
        ->test(ViewExpenseRequest::class, ['record' => $er->getRouteKey()])
        ->assertOk()
        ->assertSee("/admin/finance-attachments/{$attachment->id}/view", false);
});

it('opens Expense Request evidence in a modal instead of direct links', function () {
    Storage::fake('private');
    $employee = Employee::factory()->create();
    $er = ExpenseRequest::create([
        'request_number' => 'ER-VIEW-'.uniqid(),
        'employee_id' => $employee->id,
        'category' => 'travel',
        'title' => 'Taxi to client',
        'amount' => 120000,
        'currency' => 'IDR',
        'expense_date' => '2026-05-01',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
        'status' => ExpenseRequest::STATUS_SUBMITTED,
    ]);
    $attachment = app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->create('receipt.jpg', 4, 'image/jpeg'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $employee->user,
    );

    $infolist = ExpenseRequestResource::infolist(
        Infolist::make()
            ->name('infolist')
            ->record($er->fresh()->load('financeAttachments'))
    );
    $repeatableEntry = $infolist->getComponent('financeAttachments');
    $attachmentContainer = collect($repeatableEntry->getChildComponentContainers())->first();
    $photoEntry = $attachmentContainer->getComponent(
        fn ($component): bool => method_exists($component, 'getName') && $component->getName() === 'file_path',
    );
    $fileEntry = $attachmentContainer->getComponent(
        fn ($component): bool => method_exists($component, 'getName') && $component->getName() === 'original_filename',
    );
    $photoAction = $photoEntry->getAction('viewEvidencePhoto');
    $fileAction = $fileEntry->getAction('viewEvidenceFile');

    expect($photoEntry->getUrl())
        ->toBeNull()
        ->and($fileEntry->getUrl())
        ->toBeNull()
        ->and($photoAction)
        ->not->toBeNull()
        ->and($fileAction)
        ->not->toBeNull();

    $modalContent = $photoAction
        ->getModalContent()
        ->render();

    expect($modalContent)
        ->toContain('max-h-[75vh]')
        ->toContain("/admin/finance-attachments/{$attachment->id}/view");
});

it('ViewPaymentRequest page renders for a PR without snapshot', function () {
    $pr = PaymentRequest::create([
        'payment_request_number' => 'PR-VIEW-'.uniqid(),
        'source_type' => PaymentRequest::SOURCE_MANUAL,
        'created_by' => $this->admin->id,
        'payee_name' => 'Vendor X',
        'amount' => 500000,
        'currency' => 'IDR',
        'status' => PaymentRequest::STATUS_DRAFT,
    ]);

    Livewire::actingAs($this->admin)
        ->test(ViewPaymentRequest::class, ['record' => $pr->getRouteKey()])
        ->assertOk();
});

it('ViewPaymentRequest page renders for a PR with COO approval snapshot', function () {
    $snapshot = [
        'payee_name' => 'Vendor X',
        'bank_name' => 'BCA',
        'bank_account_last4' => '4321',
        'encrypted_bank_account_reference' => 'sha256-ref-abc',
        'amount' => '500000.00',
        'currency' => 'IDR',
    ];
    $hash = hash('sha256', json_encode($snapshot));

    $pr = PaymentRequest::create([
        'payment_request_number' => 'PR-SNAP-'.uniqid(),
        'source_type' => PaymentRequest::SOURCE_MANUAL,
        'created_by' => $this->admin->id,
        'payee_name' => 'Vendor X',
        'bank_name' => 'BCA',
        'bank_account_last4' => '4321',
        'amount' => 500000,
        'currency' => 'IDR',
        'status' => PaymentRequest::STATUS_APPROVED_BY_COO,
        'approval_snapshot_json' => $snapshot,
        'approval_snapshot_hash' => $hash,
        'coo_approved_at' => now(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(ViewPaymentRequest::class, ['record' => $pr->getRouteKey()])
        ->assertOk();
});
