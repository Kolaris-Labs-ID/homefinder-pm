<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->employee = Employee::factory()->create();
});

$draftPayload = [
    'category' => 'travel',
    'title' => 'Taxi',
    'description' => 'Grab',
    'amount' => 150000,
    'expense_date' => '2026-05-30',
    'payment_mode' => 'employee_reimbursement',
];

it('lets an employee create an ER draft and returns money as a string', function () use ($draftPayload) {
    Sanctum::actingAs($this->employee->user);

    $res = postJson('/api/hris/expense-requests', $draftPayload)->assertCreated();

    expect($res->json('data.status'))->toBe(ExpenseRequest::STATUS_DRAFT);
    expect($res->json('data.amount'))->toBeString()->toBe('150000.00');
    expect($res->json('data.request_number'))->toStartWith('ER-');
});

it('rejects submit without evidence', function () use ($draftPayload) {
    Sanctum::actingAs($this->employee->user);
    $id = postJson('/api/hris/expense-requests', $draftPayload)->json('data.id');

    postJson("/api/hris/expense-requests/{$id}/submit")->assertStatus(422);
});

it('lets an employee upload evidence then submit', function () use ($draftPayload) {
    Sanctum::actingAs($this->employee->user);
    $id = postJson('/api/hris/expense-requests', $draftPayload)->json('data.id');

    postJson("/api/hris/expense-requests/{$id}/evidence", [
        'file' => UploadedFile::fake()->createWithContent('r.pdf', 'receipt-bytes'),
    ])->assertCreated();

    postJson("/api/hris/expense-requests/{$id}/submit")->assertOk();
    expect(ExpenseRequest::find($id)->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
});

it('lists only the authenticated employee own ERs', function () {
    $mine = app(ExpenseRequestService::class)->create($this->employee, [
        'category' => 'a', 'title' => 't', 'amount' => 1000, 'expense_date' => '2026-05-01',
        'payment_mode' => 'employee_reimbursement',
    ]);
    $other = Employee::factory()->create();
    app(ExpenseRequestService::class)->create($other, [
        'category' => 'b', 'title' => 't', 'amount' => 2000, 'expense_date' => '2026-05-01',
        'payment_mode' => 'employee_reimbursement',
    ]);

    Sanctum::actingAs($this->employee->user);
    $res = getJson('/api/hris/expense-requests')->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.id'))->toBe($mine->id);
});

it('forbids viewing another employee ER (IDOR)', function () {
    $other = Employee::factory()->create();
    $theirs = app(ExpenseRequestService::class)->create($other, [
        'category' => 'b', 'title' => 't', 'amount' => 2000, 'expense_date' => '2026-05-01',
        'payment_mode' => 'employee_reimbursement',
    ]);

    Sanctum::actingAs($this->employee->user);
    getJson("/api/hris/expense-requests/{$theirs->id}")->assertForbidden();
});

it('lets finance with view_all see any ER', function () {
    $other = Employee::factory()->create();
    $theirs = app(ExpenseRequestService::class)->create($other, [
        'category' => 'b', 'title' => 't', 'amount' => 2000, 'expense_date' => '2026-05-01',
        'payment_mode' => 'employee_reimbursement',
    ]);

    $finance = User::factory()->create();
    $finance->assignRole('finance_reviewer');
    Sanctum::actingAs($finance);

    getJson("/api/hris/expense-requests/{$theirs->id}")->assertOk();
});

it('lets an employee revise an ER that needs revision', function () use ($draftPayload) {
    $svc = app(ExpenseRequestService::class);
    $er = $svc->create($this->employee, $draftPayload);
    Storage::disk('private')->put('x', 'x');
    app(\App\Services\Hris\FinanceAttachmentService::class)->store($er, UploadedFile::fake()->createWithContent('r.pdf', 'b'), \App\Models\Hris\FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);
    $svc->submit($er);
    $svc->requestRevision($er, User::factory()->create(), 'fix it');

    Sanctum::actingAs($this->employee->user);
    putJson("/api/hris/expense-requests/{$er->id}/revise", ['amount' => 175000])->assertOk();

    expect(ExpenseRequest::find($er->id)->amount)->toBe('175000.00');
    expect(ExpenseRequest::find($er->id)->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
});
