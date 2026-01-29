<?php

use App\Filament\Pages\ApprovalDashboard;
use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\PgpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature: pgp-system, Task 5.4: Approval Dashboard PGP Integration Tests
 * Validates: Requirements 4.1, 4.2, 4.3, 4.4
 */

beforeEach(function () {
    // Create necessary roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    // Create employee
    $this->employee = Employee::factory()->create();
});

describe('ApprovalDashboard Training Record Detection', function () {
    it('includes training record in module filter options', function () {
        // Verify the MODULE_TRAINING constant exists
        expect(ApprovalWorkflow::MODULE_TRAINING)->toBe('training');
    });

    it('displays training record type badge with correct color', function () {
        $dashboard = new ApprovalDashboard();

        // Test getModuleLabel for TrainingRecord
        $label = $dashboard->getModuleLabel(TrainingRecord::class);
        expect($label)->toBe('Training Record');

        // Test getModuleColor for TrainingRecord
        $color = $dashboard->getModuleColor(TrainingRecord::class);
        expect($color)->toBe('primary');
    });
});

describe('PGP Point Assignment on Approval', function () {
    it('auto-assigns 35 PGP points for free training on approval', function () {
        // Create a free training record
        $trainingRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->completed()
            ->create();

        expect($trainingRecord->pgp_points)->toBeNull();
        expect($trainingRecord->approved_at)->toBeNull();

        // Award points using PgpService (simulating approval action)
        $pgpService = app(PgpService::class);
        $pgpService->awardPoints($trainingRecord, TrainingRecord::PGP_FREE_TRAINING);

        $trainingRecord->refresh();

        expect($trainingRecord->pgp_points)->toBe(35);
        expect($trainingRecord->approved_at)->not->toBeNull();
    });

    it('assigns custom PGP points for paid training on approval', function () {
        // Create a paid training record
        $trainingRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->paidTraining()
            ->completed()
            ->create();

        expect($trainingRecord->pgp_points)->toBeNull();
        expect($trainingRecord->approved_at)->toBeNull();

        // Award points using PgpService (simulating approval action with custom points)
        $pgpService = app(PgpService::class);
        $customPoints = 20;
        $pgpService->awardPoints($trainingRecord, $customPoints);

        $trainingRecord->refresh();

        expect($trainingRecord->pgp_points)->toBe($customPoints);
        expect($trainingRecord->approved_at)->not->toBeNull();
    });
});

describe('PGP Point Validation', function () {
    it('validates paid training points are within 15-25 range', function () {
        $pgpService = app(PgpService::class);

        // Valid range
        expect($pgpService->validatePaidTrainingPoints(15))->toBeTrue();
        expect($pgpService->validatePaidTrainingPoints(20))->toBeTrue();
        expect($pgpService->validatePaidTrainingPoints(25))->toBeTrue();

        // Invalid range
        expect($pgpService->validatePaidTrainingPoints(14))->toBeFalse();
        expect($pgpService->validatePaidTrainingPoints(26))->toBeFalse();
        expect($pgpService->validatePaidTrainingPoints(0))->toBeFalse();
        expect($pgpService->validatePaidTrainingPoints(100))->toBeFalse();
    });

    it('throws exception for paid training points outside valid range', function () {
        $pgpService = app(PgpService::class);

        expect(fn () => $pgpService->getPointsForTrainingType('paid', 14))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $pgpService->getPointsForTrainingType('paid', 26))
            ->toThrow(InvalidArgumentException::class);
    });

    it('returns 35 points for free training type', function () {
        $pgpService = app(PgpService::class);

        $points = $pgpService->getPointsForTrainingType('free');
        expect($points)->toBe(35);
    });

    it('returns custom points for paid training type when valid', function () {
        $pgpService = app(PgpService::class);

        $points = $pgpService->getPointsForTrainingType('paid', 20);
        expect($points)->toBe(20);
    });
});

describe('Training Type Detection', function () {
    it('correctly identifies free training', function () {
        $trainingRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->create();

        expect($trainingRecord->isFreeTraining())->toBeTrue();
        expect($trainingRecord->isPaidTraining())->toBeFalse();
    });

    it('correctly identifies paid training', function () {
        $trainingRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->paidTraining()
            ->create();

        expect($trainingRecord->isPaidTraining())->toBeTrue();
        expect($trainingRecord->isFreeTraining())->toBeFalse();
    });
});

describe('PGP Approval Recording', function () {
    it('records both pgp_points and approved_at on approval', function () {
        $trainingRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->completed()
            ->create();

        // Initially not approved
        expect($trainingRecord->isApprovedForPgp())->toBeFalse();

        // Award points
        $pgpService = app(PgpService::class);
        $pgpService->awardPoints($trainingRecord, 35);

        $trainingRecord->refresh();

        // Now approved
        expect($trainingRecord->isApprovedForPgp())->toBeTrue();
        expect($trainingRecord->pgp_points)->toBe(35);
        expect($trainingRecord->approved_at)->not->toBeNull();
    });
});
