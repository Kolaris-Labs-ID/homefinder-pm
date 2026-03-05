<?php

use App\Models\Hris\ClientVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Feature: sudden-client-visit-logic
 * Unit tests for ClientVisit model changes.
 */

test('TYPE_PLANNED constant has correct value', function () {
    expect(ClientVisit::TYPE_PLANNED)->toBe('planned');
})->group('sudden-client-visit-logic');

test('TYPE_SUDDEN constant has correct value', function () {
    expect(ClientVisit::TYPE_SUDDEN)->toBe('sudden');
})->group('sudden-client-visit-logic');

test('visit_type is in fillable', function () {
    expect((new ClientVisit)->getFillable())->toContain('visit_type');
})->group('sudden-client-visit-logic');

test('getVisitTypeLabels returns correct labels', function () {
    $labels = ClientVisit::getVisitTypeLabels();

    expect($labels)->toHaveKey(ClientVisit::TYPE_PLANNED)
        ->and($labels)->toHaveKey(ClientVisit::TYPE_SUDDEN)
        ->and($labels[ClientVisit::TYPE_PLANNED])->toBe('Planned')
        ->and($labels[ClientVisit::TYPE_SUDDEN])->toBe('Sudden');
})->group('sudden-client-visit-logic');

test('visit_type column exists in client_visits table with default planned', function () {
    expect(Schema::hasColumn('client_visits', 'visit_type'))->toBeTrue();

    $employee = \App\Models\Hris\Employee::factory()->create();
    $visit = ClientVisit::create([
        'employee_id' => $employee->id,
        'client_name' => 'Test Client',
        'client_address' => '123 Test St',
        'purpose' => ClientVisit::PURPOSE_AUDIT,
        'visit_date' => today()->addDay(),
        'status' => ClientVisit::STATUS_PLANNED,
    ]);

    expect($visit->visit_type)->toBe(ClientVisit::TYPE_PLANNED);
})->group('sudden-client-visit-logic');
