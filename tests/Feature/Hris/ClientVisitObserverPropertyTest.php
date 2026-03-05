<?php

/**
 * Property-Based Tests for ClientVisitObserver
 *
 * Feature: sudden-client-visit-logic
 * Tests verify correctness properties defined in the design document
 * using 100-iteration randomized testing with Faker.
 */

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: sudden-client-visit-logic, Property 1: Future visit dates are classified as planned
 *
 * For any ClientVisit created with a visit_date strictly greater than today,
 * the observer SHALL set visit_type to 'planned'.
 *
 * Validates: Requirements 2.1
 */
test('Property 1: Future visit dates are classified as planned', function () {
    $employee = Employee::factory()->create();

    for ($i = 0; $i < 100; $i++) {
        $futureDays = rand(1, 365);
        $futureDate = today()->addDays($futureDays);

        $visit = ClientVisit::create([
            'employee_id' => $employee->id,
            'client_name' => fake()->company(),
            'client_address' => fake()->address(),
            'purpose' => fake()->randomElement(ClientVisit::getPurposes()),
            'visit_date' => $futureDate,
            'status' => ClientVisit::STATUS_PLANNED,
        ]);

        expect($visit->visit_type)->toBe(
            ClientVisit::TYPE_PLANNED,
            "Expected 'planned' for future date +{$futureDays} days, got '{$visit->visit_type}'"
        );

        $visit->forceDelete();
    }
})->group('property-test', 'sudden-client-visit-logic');

/**
 * Feature: sudden-client-visit-logic, Property 3: Same-day visit updates existing attendance log
 *
 * For any employee who has an existing AttendanceLog for today, when a ClientVisit
 * is created with visit_date equal to today, the observer SHALL update that
 * AttendanceLog's status to 'visit', source to 'visit', and client_visit_id.
 *
 * Validates: Requirements 4.2, 4.3, 4.4
 */
test('Property 3: Same-day visit updates existing attendance log', function () {
    for ($i = 0; $i < 100; $i++) {
        $employee = Employee::factory()->create();

        $existingLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => today(),
            'status' => fake()->randomElement([
                AttendanceLog::STATUS_PRESENT,
                AttendanceLog::STATUS_LATE,
            ]),
            'source' => AttendanceLog::SOURCE_MOBILE,
        ]);

        $visit = ClientVisit::create([
            'employee_id' => $employee->id,
            'client_name' => fake()->company(),
            'client_address' => fake()->address(),
            'purpose' => fake()->randomElement(ClientVisit::getPurposes()),
            'visit_date' => today(),
            'status' => ClientVisit::STATUS_PLANNED,
        ]);

        $existingLog->refresh();

        expect($existingLog->status)->toBe(AttendanceLog::STATUS_VISIT)
            ->and($existingLog->source)->toBe(AttendanceLog::SOURCE_VISIT)
            ->and($existingLog->client_visit_id)->toBe($visit->id);

        // Clean up for next iteration
        AttendanceLog::where('employee_id', $employee->id)->delete();
        $visit->forceDelete();
        $employee->delete();
    }
})->group('property-test', 'sudden-client-visit-logic');

/**
 * Feature: sudden-client-visit-logic, Property 4: Same-day visit creates attendance log when none exists
 *
 * For any employee who does NOT have an AttendanceLog for today, when a ClientVisit
 * is created with visit_date equal to today, the observer SHALL create a new
 * AttendanceLog with status='visit', source='visit', date=today, client_visit_id set.
 *
 * Validates: Requirements 4.5
 */
test('Property 4: Same-day visit creates attendance log when none exists', function () {
    for ($i = 0; $i < 100; $i++) {
        $employee = Employee::factory()->create();

        // Ensure no attendance log exists for today
        AttendanceLog::forEmployee($employee->id)->forDate(today())->delete();

        $visit = ClientVisit::create([
            'employee_id' => $employee->id,
            'client_name' => fake()->company(),
            'client_address' => fake()->address(),
            'purpose' => fake()->randomElement(ClientVisit::getPurposes()),
            'visit_date' => today(),
            'status' => ClientVisit::STATUS_PLANNED,
        ]);

        $log = AttendanceLog::forEmployee($employee->id)->forDate(today())->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe(AttendanceLog::STATUS_VISIT)
            ->and($log->source)->toBe(AttendanceLog::SOURCE_VISIT)
            ->and($log->client_visit_id)->toBe($visit->id)
            ->and($log->date->toDateString())->toBe(today()->toDateString());

        // Clean up for next iteration
        AttendanceLog::where('employee_id', $employee->id)->delete();
        $visit->forceDelete();
        $employee->delete();
    }
})->group('property-test', 'sudden-client-visit-logic');
