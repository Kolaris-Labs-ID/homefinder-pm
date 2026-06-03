<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use Database\Seeders\FinanceRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\postJson;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->employee = Employee::factory()->create();
});

$payload = [
    'category' => 'travel',
    'title' => 'Taxi',
    'amount' => 150000,
    'expense_date' => '2026-05-30',
    'payment_mode' => 'employee_reimbursement',
];

it('returns the same ER (200) for a repeated create with the same client_request_id', function () use ($payload) {
    Sanctum::actingAs($this->employee->user);
    $body = $payload + ['client_request_id' => 'cli-abc-123'];

    $first = postJson('/api/hris/expense-requests', $body)->assertCreated();
    $second = postJson('/api/hris/expense-requests', $body)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    expect(ExpenseRequest::where('client_request_id', 'cli-abc-123')->count())->toBe(1);
});

it('still creates a new ER each time when no client_request_id is sent', function () use ($payload) {
    Sanctum::actingAs($this->employee->user);

    postJson('/api/hris/expense-requests', $payload)->assertCreated();
    postJson('/api/hris/expense-requests', $payload)->assertCreated();

    expect(ExpenseRequest::where('employee_id', $this->employee->id)->count())->toBe(2);
});

it('lets two different employees reuse the same client_request_id', function () use ($payload) {
    $other = Employee::factory()->create();
    $key = ['client_request_id' => 'shared-key-1'];

    Sanctum::actingAs($this->employee->user);
    $mine = postJson('/api/hris/expense-requests', $payload + $key)->assertCreated();

    Sanctum::actingAs($other->user);
    $theirs = postJson('/api/hris/expense-requests', $payload + $key)->assertCreated();

    expect($theirs->json('data.id'))->not->toBe($mine->json('data.id'));
    expect(ExpenseRequest::where('client_request_id', 'shared-key-1')->count())->toBe(2);
});

it('does not duplicate the ER when create is replayed after a full upload+submit flow', function () use ($payload) {
    Sanctum::actingAs($this->employee->user);
    $body = $payload + ['client_request_id' => 'flow-key-9'];

    $id = postJson('/api/hris/expense-requests', $body)->assertCreated()->json('data.id');
    postJson("/api/hris/expense-requests/{$id}/evidence", [
        'file' => UploadedFile::fake()->createWithContent('r.pdf', 'receipt-bytes'),
    ])->assertCreated();
    postJson("/api/hris/expense-requests/{$id}/submit")->assertOk();

    $replay = postJson('/api/hris/expense-requests', $body)->assertOk();

    expect($replay->json('data.id'))->toBe($id);
    expect($replay->json('data.status'))->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect(ExpenseRequest::where('client_request_id', 'flow-key-9')->count())->toBe(1);
});
