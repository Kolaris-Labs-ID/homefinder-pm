<?php

/**
 * PHI Growth Point (PGP) — end-to-end and edge-case coverage:
 * training workflow init on create, approval/PGP wiring, bulk PGP, model & policy behaviour.
 */

use App\Filament\Pages\ApprovalDashboard;
use App\Filament\Resources\TrainingRecordResource\Pages\CreateTrainingRecord;
use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\PgpService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr_manager', 'guard_name' => 'web']);

    $this->approvalService = app(ApprovalWorkflowService::class);
    $this->pgpService = app(PgpService::class);
});

// --- TrainingRecord::markAsApproved & workflow completion ---

describe('TrainingRecord markAsApproved and workflow completion', function () {
    it('markAsApproved does not mutate training status enum', function () {
        $employee = Employee::factory()->create();
        $record = TrainingRecord::factory()
            ->forEmployee($employee)
            ->planned()
            ->freeTraining()
            ->create();

        $record->markAsApproved();
        $record->refresh();

        expect($record->status)->toBe(TrainingRecord::STATUS_PLANNED)
            ->and($record->status)->not->toBe('approved');
    });

    it('keeps completed status after markAsApproved', function () {
        $employee = Employee::factory()->create();
        $record = TrainingRecord::factory()
            ->forEmployee($employee)
            ->completed()
            ->freeTraining()
            ->create();

        $record->markAsApproved();
        $record->refresh();

        expect($record->status)->toBe(TrainingRecord::STATUS_COMPLETED);
    });

    it('does not set invalid status when final approval step completes via service', function () {
        $approver = User::factory()->create();

        ApprovalWorkflow::create([
            'name' => 'Training single-step',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        $employee = Employee::factory()->create();
        $training = TrainingRecord::factory()
            ->forEmployee($employee)
            ->completed()
            ->freeTraining()
            ->create();

        $this->approvalService->initializeWorkflow($training, ApprovalWorkflow::MODULE_TRAINING);

        $step = ApprovalStep::where('approvable_type', TrainingRecord::class)
            ->where('approvable_id', $training->id)
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->first();

        expect($step)->not->toBeNull();

        $this->approvalService->approve($step, $approver, 'OK');

        $training->refresh();

        expect($training->status)->toBe(TrainingRecord::STATUS_COMPLETED)
            ->and($training->status)->not->toBe('approved');
    });
});

// --- initializeWorkflow for training module ---

describe('ApprovalWorkflowService training module initialization', function () {
    it('creates approval steps when an active training workflow exists', function () {
        $approver = User::factory()->create();

        ApprovalWorkflow::create([
            'name' => 'Training workflow',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
                ['order' => 2, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        $employee = Employee::factory()->create();
        $training = TrainingRecord::factory()
            ->forEmployee($employee)
            ->planned()
            ->create();

        $this->approvalService->initializeWorkflow($training, ApprovalWorkflow::MODULE_TRAINING);

        $steps = ApprovalStep::where('approvable_type', TrainingRecord::class)
            ->where('approvable_id', $training->id)
            ->orderBy('step_order')
            ->get();

        expect($steps)->toHaveCount(2)
            ->and($steps[0]->status)->toBe(ApprovalStep::STATUS_PENDING)
            ->and($steps[1]->status)->toBe(ApprovalStep::STATUS_SKIPPED);
    });

    it('creates no steps when no active training workflow exists', function () {
        $employee = Employee::factory()->create();
        $training = TrainingRecord::factory()->forEmployee($employee)->create();

        $this->approvalService->initializeWorkflow($training, ApprovalWorkflow::MODULE_TRAINING);

        expect(
            ApprovalStep::where('approvable_type', TrainingRecord::class)
                ->where('approvable_id', $training->id)
                ->count()
        )->toBe(0);
    });

    it('ignores inactive training workflows', function () {
        $approver = User::factory()->create();

        ApprovalWorkflow::create([
            'name' => 'Inactive training',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => false,
        ]);

        $employee = Employee::factory()->create();
        $training = TrainingRecord::factory()->forEmployee($employee)->create();

        $this->approvalService->initializeWorkflow($training, ApprovalWorkflow::MODULE_TRAINING);

        expect(
            ApprovalStep::where('approvable_type', TrainingRecord::class)
                ->where('approvable_id', $training->id)
                ->count()
        )->toBe(0);
    });
});

// --- CreateTrainingRecord Filament + workflow ---

describe('CreateTrainingRecord PGP workflow hook', function () {
    it('initializes training approval steps after Filament create when workflow is active', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        ApprovalWorkflow::create([
            'name' => 'Training for Filament',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $admin->id],
            ],
            'is_active' => true,
        ]);

        $employee = Employee::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateTrainingRecord::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'training_name' => 'Comprehensive PGP Filament Create',
                'status' => TrainingRecord::STATUS_PLANNED,
                'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = TrainingRecord::where('training_name', 'Comprehensive PGP Filament Create')->first();
        expect($record)->not->toBeNull();

        expect(
            ApprovalStep::where('approvable_type', TrainingRecord::class)
                ->where('approvable_id', $record->id)
                ->count()
        )->toBe(1);
    });

    it('does not create approval steps after create when no active training workflow', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $employee = Employee::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateTrainingRecord::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'training_name' => 'No Workflow Training',
                'status' => TrainingRecord::STATUS_PLANNED,
                'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = TrainingRecord::where('training_name', 'No Workflow Training')->first();
        expect($record)->not->toBeNull();

        expect(
            ApprovalStep::where('approvable_type', TrainingRecord::class)
                ->where('approvable_id', $record->id)
                ->count()
        )->toBe(0);
    });
});

// --- mutateFormDataBeforeCreate (employee cannot pick another employee) ---

describe('CreateTrainingRecord mutateFormDataBeforeCreate', function () {
    it('forces employee_id to the hr-admin own employee when they cannot manage others', function () {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole('hr-admin');
        $ownEmployee = Employee::factory()->create(['user_id' => $hrAdmin->id]);
        $otherEmployee = Employee::factory()->create();

        $component = Livewire::actingAs($hrAdmin)->test(CreateTrainingRecord::class);

        $method = new \ReflectionMethod(CreateTrainingRecord::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = [
            'employee_id' => $otherEmployee->id,
            'training_name' => 'Should attach to self',
        ];

        $result = $method->invoke($component->instance(), $data);

        expect($result['employee_id'])->toBe($ownEmployee->id);
    });

    it('does not override employee_id when user can manage others (super_admin)', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();

        $component = Livewire::actingAs($admin)->test(CreateTrainingRecord::class);

        $method = new \ReflectionMethod(CreateTrainingRecord::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $data = [
            'employee_id' => $employeeB->id,
            'training_name' => 'HR creates for B',
        ];

        $result = $method->invoke($component->instance(), $data);

        expect($result['employee_id'])->toBe($employeeB->id);
    });
});

// --- TrainingRecordPolicy create ---

describe('TrainingRecordPolicy create scenarios', function () {
    it('allows create for user linked to an employee profile', function () {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        expect($user->can('create', TrainingRecord::class))->toBeTrue();
    });

    it('allows create for hr-admin without employee record', function () {
        $user = User::factory()->create();
        $user->assignRole('hr-admin');

        expect($user->can('create', TrainingRecord::class))->toBeTrue();
    });

    it('denies create for user with no employee and no hr-admin / permission', function () {
        $user = User::factory()->create();

        expect($user->can('create', TrainingRecord::class))->toBeFalse();
    });
});

// --- ApprovalDashboard PGP helpers (reflection) ---

describe('ApprovalDashboard awardPgpForApprovedTrainingStep', function () {
    beforeEach(function () {
        $this->approver = User::factory()->create();
        $this->approver->assignRole('super_admin');
        $this->employeeModel = Employee::factory()->create();

        $this->invokeAwardPgp = function (ApprovalDashboard $dashboard, TrainingRecord $record, ?int $paidPoints, bool $bulkDefault): void {
            $m = new \ReflectionMethod(ApprovalDashboard::class, 'awardPgpForApprovedTrainingStep');
            $m->setAccessible(true);
            $m->invoke($dashboard, $record, $paidPoints, $bulkDefault);
        };
    });

    it('awards 35 PGP for free training (single-approve path)', function () {
        $record = TrainingRecord::factory()
            ->forEmployee($this->employeeModel)
            ->freeTraining()
            ->completed()
            ->create(['pgp_points' => null, 'approved_at' => null]);

        $dashboard = new ApprovalDashboard;
        $dashboard->boot(app(ApprovalWorkflowService::class), app(PgpService::class));

        ($this->invokeAwardPgp)($dashboard, $record, null, false);

        $record->refresh();
        expect($record->pgp_points)->toBe(35)
            ->and($record->approved_at)->not->toBeNull();
    });

    it('awards explicit PGP for paid training when points provided', function () {
        $record = TrainingRecord::factory()
            ->forEmployee($this->employeeModel)
            ->paidTraining()
            ->completed()
            ->create(['pgp_points' => null, 'approved_at' => null]);

        $dashboard = new ApprovalDashboard;
        $dashboard->boot(app(ApprovalWorkflowService::class), app(PgpService::class));

        ($this->invokeAwardPgp)($dashboard, $record, 22, false);

        $record->refresh();
        expect($record->pgp_points)->toBe(22);
    });

    it('does not award paid training when points are null and bulk default is off', function () {
        $record = TrainingRecord::factory()
            ->forEmployee($this->employeeModel)
            ->paidTraining()
            ->completed()
            ->create(['pgp_points' => null, 'approved_at' => null]);

        $dashboard = new ApprovalDashboard;
        $dashboard->boot(app(ApprovalWorkflowService::class), app(PgpService::class));

        ($this->invokeAwardPgp)($dashboard, $record, null, false);

        $record->refresh();
        expect($record->pgp_points)->toBeNull()
            ->and($record->approved_at)->toBeNull();
    });

    it('uses midpoint PGP for paid training when bulk default is on and points unset', function () {
        $record = TrainingRecord::factory()
            ->forEmployee($this->employeeModel)
            ->paidTraining()
            ->completed()
            ->create(['pgp_points' => null, 'approved_at' => null]);

        $dashboard = new ApprovalDashboard;
        $dashboard->boot(app(ApprovalWorkflowService::class), app(PgpService::class));

        ($this->invokeAwardPgp)($dashboard, $record, null, true);

        $record->refresh();
        expect($record->pgp_points)->toBe(20)
            ->and($record->approved_at)->not->toBeNull();
    });

    it('does not award invalid paid points when explicitly out of range', function () {
        $record = TrainingRecord::factory()
            ->forEmployee($this->employeeModel)
            ->paidTraining()
            ->completed()
            ->create(['pgp_points' => null, 'approved_at' => null]);

        $dashboard = new ApprovalDashboard;
        $dashboard->boot(app(ApprovalWorkflowService::class), app(PgpService::class));

        ($this->invokeAwardPgp)($dashboard, $record, 99, false);

        $record->refresh();
        expect($record->pgp_points)->toBeNull();
    });
});

describe('ApprovalDashboard bulkApprove PGP integration', function () {
    it('awards PGP for each training record step after bulk service approval', function () {
        $approver = User::factory()->create();
        $approver->assignRole('super_admin');

        ApprovalWorkflow::create([
            'name' => 'Training bulk',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        $employee = Employee::factory()->create();

        $freeTraining = TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->create();

        $paidTraining = TrainingRecord::factory()
            ->forEmployee($employee)
            ->paidTraining()
            ->create();

        $this->approvalService->initializeWorkflow($freeTraining, ApprovalWorkflow::MODULE_TRAINING);
        $this->approvalService->initializeWorkflow($paidTraining, ApprovalWorkflow::MODULE_TRAINING);

        $stepFree = ApprovalStep::where('approvable_id', $freeTraining->id)->first();
        $stepPaid = ApprovalStep::where('approvable_id', $paidTraining->id)->first();

        $component = Livewire::actingAs($approver)->test(ApprovalDashboard::class);

        $bulk = new \ReflectionMethod(ApprovalDashboard::class, 'bulkApprove');
        $bulk->setAccessible(true);
        $bulk->invoke($component->instance(), new EloquentCollection([$stepFree, $stepPaid]), 'bulk notes');

        $freeTraining->refresh();
        $paidTraining->refresh();

        expect($freeTraining->pgp_points)->toBe(35)
            ->and($paidTraining->pgp_points)->toBe(20)
            ->and($freeTraining->approved_at)->not->toBeNull()
            ->and($paidTraining->approved_at)->not->toBeNull();
    });
});

// --- PgpService edge cases ---

describe('PgpService edge cases', function () {
    it('getProgressPercentage returns zero for zero points', function () {
        expect($this->pgpService->getProgressPercentage(0))->toBe(0.0);
    });

    it('getProgressPercentage caps at 100 for very large totals', function () {
        expect($this->pgpService->getProgressPercentage(500))->toBe(100.0);
    });

    it('calculateReward returns zero below target', function () {
        expect($this->pgpService->calculateReward(149))->toBe(0);
    });

    it('calculateReward returns base at exactly 150', function () {
        expect($this->pgpService->calculateReward(150))->toBe(150000);
    });

    it('throws for invalid training type in getPointsForTrainingType', function () {
        expect(fn () => $this->pgpService->getPointsForTrainingType('invalid'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

// --- isApprovedForPgp boundary ---

describe('TrainingRecord isApprovedForPgp', function () {
    it('is false when pgp_points is zero with approved_at set', function () {
        $employee = Employee::factory()->create();
        $record = TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->create([
                'pgp_points' => 0,
                'approved_at' => now(),
            ]);

        expect($record->isApprovedForPgp())->toBeFalse();
    });

    it('is true when pgp_points positive and approved_at set', function () {
        $employee = Employee::factory()->create();
        $record = TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create();

        expect($record->isApprovedForPgp())->toBeTrue();
    });
});
