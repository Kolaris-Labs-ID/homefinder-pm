<?php

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    $this->employee = Employee::factory()->create();
    Sanctum::actingAs($this->employee->user);
});

it('rejects an ambiguous thousands-separated reimbursement amount "10.000"', function () {
    postJson('/api/hris/reimbursements', [
        'type' => Reimbursement::TYPE_TRANSPORT,
        'amount' => '10.000',
    ])->assertStatus(422)->assertJsonValidationErrors('amount');
});

it('accepts a clean integer reimbursement amount', function () {
    postJson('/api/hris/reimbursements', [
        'type' => Reimbursement::TYPE_TRANSPORT,
        'amount' => 10000,
    ])->assertCreated();
});
