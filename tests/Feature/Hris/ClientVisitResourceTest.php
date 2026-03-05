<?php

/**
 * Feature tests for ClientVisitResource
 *
 * Feature: sudden-client-visit-logic
 */

use App\Filament\Resources\ClientVisitResource;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Feature: sudden-client-visit-logic, Property 2: Visit date validation rejects past dates
 *
 * Validates: Requirements 3.2, 3.5, 6.1, 6.2
 */
test('Property 2: Visit date validation rejects past dates', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 100; $i++) {
        $pastDate = today()->subDays(rand(1, 365))->toDateString();

        $response = $this->postJson('/api/hris/visits', [
            'employee_id' => $employee->id,
            'client_name' => fake()->company(),
            'client_address' => fake()->address(),
            'purpose' => ClientVisit::PURPOSE_AUDIT,
            'visit_date' => $pastDate,
            'status' => ClientVisit::STATUS_PLANNED,
        ]);

        $response->assertStatus(422);
        expect($response->json('errors'))->toHaveKey('visit_date');
    }
})->group('property-test', 'sudden-client-visit-logic');

test('visit date validation accepts today and future dates', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 10; $i++) {
        $date = today()->addDays(rand(0, 365))->toDateString();

        $response = $this->postJson('/api/hris/visits', [
            'employee_id' => $employee->id,
            'client_name' => fake()->company(),
            'client_address' => fake()->address(),
            'purpose' => ClientVisit::PURPOSE_AUDIT,
            'visit_date' => $date,
            'status' => ClientVisit::STATUS_PLANNED,
        ]);

        $response->assertStatus(201);
    }
})->group('sudden-client-visit-logic');

test('ClientVisitResource form contains all required fields', function () {
    $fields = collect(ClientVisitResource::getFormSchema())
        ->flatMap(fn ($section) => method_exists($section, 'getChildComponents')
            ? $section->getChildComponents()
            : [$section])
        ->map(fn ($field) => method_exists($field, 'getName') ? $field->getName() : null)
        ->filter()
        ->values()
        ->toArray();

    foreach (['employee_id', 'visit_date', 'client_name', 'client_address', 'purpose', 'status'] as $field) {
        expect($fields)->toContain($field);
    }
})->group('sudden-client-visit-logic');

test('ClientVisitResource table contains required columns', function () {
    $columns = collect(ClientVisitResource::getTableColumns())
        ->map(fn ($col) => $col->getName())
        ->toArray();

    foreach (['visit_date', 'visit_type', 'purpose', 'status'] as $col) {
        expect($columns)->toContain($col);
    }
})->group('sudden-client-visit-logic');

test('creating a visit with today date sets visit_type to sudden', function () {
    $employee = Employee::factory()->create();

    $visit = ClientVisit::create([
        'employee_id' => $employee->id,
        'client_name' => 'Test Client',
        'client_address' => '123 Test St',
        'purpose' => ClientVisit::PURPOSE_AUDIT,
        'visit_date' => today(),
        'status' => ClientVisit::STATUS_PLANNED,
    ]);

    expect($visit->visit_type)->toBe(ClientVisit::TYPE_SUDDEN);
})->group('sudden-client-visit-logic');
