<?php

use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const BACKFILL_MIGRATION = 'database/migrations/2026_06_23_000001_backfill_client_visit_employee_ids.php';

// An employee whose user_id is ALSO a valid (different) employee id — i.e. the
// only shape in which the bug's FK-constrained corruption could have persisted.
function corruptableEmployee(): Employee
{
    User::factory()->count(5)->create(); // offset user ids ahead of employee ids
    $emps = collect();
    for ($i = 0; $i < 12; $i++) {
        $emps->push(Employee::factory()->create([
            'user_id' => User::factory()->create(['name' => "Emp {$i}"])->id,
            'is_active' => true,
        ]));
    }

    return $emps->first(fn (Employee $e) => $e->user_id !== $e->id && Employee::whereKey($e->user_id)->exists());
}

test('migration flags corrupt visits and leaves correct visits alone', function () {
    $bob = corruptableEmployee();
    $alice = Employee::query()->whereKeyNot($bob->id)->first();

    $corrupt = ClientVisit::create([
        'employee_id' => $bob->user_id, // bug: should be $bob->id
        'client_name' => 'Corrupt Co', 'client_address' => 'x',
        'purpose' => ClientVisit::PURPOSE_AUDIT, 'visit_date' => today(),
        'status' => ClientVisit::STATUS_PLANNED,
    ]);
    $correct = ClientVisit::create([
        'employee_id' => $alice->id,
        'client_name' => 'Correct Co', 'client_address' => 'y',
        'purpose' => ClientVisit::PURPOSE_AUDIT, 'visit_date' => today(),
        'status' => ClientVisit::STATUS_PLANNED,
    ]);

    // Re-run only the backfill migration so up() repopulates from existing data.
    $this->artisan('migrate:rollback', ['--path' => BACKFILL_MIGRATION])->assertOk();
    $this->artisan('migrate', ['--path' => BACKFILL_MIGRATION])->assertOk();

    $flagged = DB::table('client_visit_employee_backfill')->pluck('suggested_employee_id', 'client_visit_id');

    expect($flagged->has($corrupt->id))->toBeTrue()
        ->and((int) $flagged[$corrupt->id])->toBe($bob->id)
        ->and($flagged->has($correct->id))->toBeFalse();
})->group('sudden-client-visit-logic');

test('command applies, is dry-run by default, and is reversible', function () {
    $bob = corruptableEmployee();

    $visit = ClientVisit::create([
        'employee_id' => $bob->user_id,
        'client_name' => 'Corrupt Co', 'client_address' => 'x',
        'purpose' => ClientVisit::PURPOSE_AUDIT, 'visit_date' => today(),
        'status' => ClientVisit::STATUS_PLANNED,
    ]);
    DB::table('client_visit_employee_backfill')->insert([
        'client_visit_id' => $visit->id,
        'old_employee_id' => $bob->user_id,
        'suggested_employee_id' => $bob->id,
        'suggested_employee_name' => $bob->user->name,
        'has_activity' => false, 'applied' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('client-visits:apply-employee-backfill')->assertOk();
    expect($visit->fresh()->employee_id)->toBe($bob->user_id); // dry-run: unchanged

    $this->artisan('client-visits:apply-employee-backfill --apply')->assertOk();
    expect($visit->fresh()->employee_id)->toBe($bob->id)
        ->and($visit->fresh()->employee->user->name)->toBe($bob->user->name);

    $this->artisan('client-visits:apply-employee-backfill --revert')->assertOk();
    expect($visit->fresh()->employee_id)->toBe($bob->user_id);
})->group('sudden-client-visit-logic');

test('command skips activity rows unless forced', function () {
    $bob = corruptableEmployee();

    $visit = ClientVisit::create([
        'employee_id' => $bob->user_id,
        'client_name' => 'Active Co', 'client_address' => 'x',
        'purpose' => ClientVisit::PURPOSE_AUDIT, 'visit_date' => today(),
        'status' => ClientVisit::STATUS_IN_PROGRESS, 'actual_check_in' => now(),
    ]);
    DB::table('client_visit_employee_backfill')->insert([
        'client_visit_id' => $visit->id,
        'old_employee_id' => $bob->user_id,
        'suggested_employee_id' => $bob->id,
        'has_activity' => true, 'applied' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('client-visits:apply-employee-backfill --apply')->assertOk();
    expect($visit->fresh()->employee_id)->toBe($bob->user_id); // skipped

    $this->artisan('client-visits:apply-employee-backfill --apply --include-activity')->assertOk();
    expect($visit->fresh()->employee_id)->toBe($bob->id);
})->group('sudden-client-visit-logic');
