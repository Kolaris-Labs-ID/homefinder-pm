<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    $this->employee = Employee::factory()->create();
});

function erDraft(array $overrides = []): ExpenseRequest
{
    return app(ExpenseRequestService::class)->create(test()->employee, array_merge([
        'category' => 'travel',
        'title' => 'Taxi',
        'amount' => 150000,
        'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
    ], $overrides));
}

it('does not mask an unexpected infrastructure error as a client 422', function () {
    $er = erDraft();
    app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->createWithContent('receipt.pdf', 'x'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $this->employee->user,
    );
    Sanctum::actingAs($this->employee->user);

    // Simulate a genuine backend fault (e.g. a masked QueryException/deadlock).
    $this->mock(ExpenseRequestService::class, function ($mock) {
        $mock->shouldReceive('submit')->andThrow(new \RuntimeException('SQLSTATE[40001]: deadlock detected'));
    });

    postJson("/api/hris/expense-requests/{$er->id}/submit")->assertStatus(500);
});

it('maps a business-rule violation to a 422 with a safe, non-sensitive message', function () {
    $er = erDraft(); // no evidence → business-rule failure
    Sanctum::actingAs($this->employee->user);

    postJson("/api/hris/expense-requests/{$er->id}/submit")
        ->assertStatus(422)
        ->assertJsonPath('message', 'An expense request requires evidence before it can be submitted.');
});
