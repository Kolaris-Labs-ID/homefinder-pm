<?php

use App\Models\Hris\Employee;
use Database\Seeders\FinanceRolePermissionSeeder;
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
    Sanctum::actingAs($this->employee->user);
});

$base = [
    'category' => 'travel',
    'title' => 'Taxi',
    'expense_date' => '2026-05-30',
    'payment_mode' => 'employee_reimbursement',
];

it('rejects an ambiguous thousands-separated amount "10.000"', function () use ($base) {
    // Indonesian "10.000" (ten thousand) is numerically 10.0 → would silently collapse to 10.00.
    postJson('/api/hris/expense-requests', $base + ['amount' => '10.000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');
});

it('rejects an amount with more than two decimal places', function () use ($base) {
    postJson('/api/hris/expense-requests', $base + ['amount' => '10.5000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');
});

it('rejects an amount with a comma group separator', function () use ($base) {
    postJson('/api/hris/expense-requests', $base + ['amount' => '10,000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');
});

it('accepts a clean integer amount from the mobile client', function () use ($base) {
    postJson('/api/hris/expense-requests', $base + ['amount' => 10000])
        ->assertCreated()
        ->assertJsonPath('data.amount', '10000.00');
});

it('accepts a clean two-decimal amount', function () use ($base) {
    postJson('/api/hris/expense-requests', $base + ['amount' => '10000.50'])
        ->assertCreated()
        ->assertJsonPath('data.amount', '10000.50');
});
