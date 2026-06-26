<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FinanceCenterFlowFactory;

beforeEach(function () {
    Storage::fake('private');
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();

    $this->employee = Employee::factory()->create();
    $this->er = app(ExpenseRequestService::class)->create($this->employee, [
        'category' => 'travel',
        'title' => 'Taxi receipt',
        'amount' => 150000,
        'expense_date' => '2026-06-01',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
    ]);
});

function webViewAttachmentForEr(ExpenseRequest $er, string $name = 'receipt.jpg', string $mime = 'image/jpeg'): FinanceAttachment
{
    return app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->create($name, 4, $mime),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $er->employee->user,
    );
}

it('lets a finance reviewer view ER evidence inline from the admin dashboard', function () {
    $attachment = webViewAttachmentForEr($this->er);
    $reviewer = FinanceCenterFlowFactory::financeReviewer();

    $res = $this->actingAs($reviewer)
        ->get("/admin/finance-attachments/{$attachment->id}/view");

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('inline');
    expect($res->headers->get('content-type'))->toContain('image/jpeg');
    expect($res->headers->get('x-content-type-options'))->toBe('nosniff');
});

it('forces unsafe finance attachments to download as opaque bytes', function () {
    $attachment = webViewAttachmentForEr($this->er, 'receipt.html', 'text/html');
    $reviewer = FinanceCenterFlowFactory::financeReviewer();

    $res = $this->actingAs($reviewer)
        ->get("/admin/finance-attachments/{$attachment->id}/view");

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment');
    expect($res->headers->get('content-type'))->toContain('application/octet-stream');
    expect($res->headers->get('x-content-type-options'))->toBe('nosniff');
});

it('forbids a user without finance attachment access', function () {
    $attachment = webViewAttachmentForEr($this->er);
    $outsider = \App\Models\User::factory()->create();

    $this->actingAs($outsider)
        ->get("/admin/finance-attachments/{$attachment->id}/view")
        ->assertForbidden();
});
