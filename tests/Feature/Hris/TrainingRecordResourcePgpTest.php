<?php

use App\Filament\Resources\TrainingRecordResource;
use App\Filament\Resources\TrainingRecordResource\Pages\CreateTrainingRecord;
use App\Filament\Resources\TrainingRecordResource\Pages\EditTrainingRecord;
use App\Filament\Resources\TrainingRecordResource\Pages\ListTrainingRecords;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
        $this->admin->assignRole($role);
    }
    $this->employee = Employee::factory()->create();
});

describe('TrainingRecordResource Form PGP Fields', function () {
    it('displays training_type field in create form', function () {
        Livewire::actingAs($this->admin)
            ->test(CreateTrainingRecord::class)
            ->assertFormFieldExists('training_type');
    });

    it('displays training_type field in edit form', function () {
        $record = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->create();

        Livewire::actingAs($this->admin)
            ->test(EditTrainingRecord::class, ['record' => $record->id])
            ->assertFormFieldExists('training_type');
    });

    it('can create training record with free training type', function () {
        Livewire::actingAs($this->admin)
            ->test(CreateTrainingRecord::class)
            ->fillForm([
                'employee_id' => $this->employee->id,
                'training_name' => 'Test Free Training',
                'status' => TrainingRecord::STATUS_PLANNED,
                'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(TrainingRecord::where('training_name', 'Test Free Training')->exists())->toBeTrue();
        $record = TrainingRecord::where('training_name', 'Test Free Training')->first();
        expect($record->training_type)->toBe(TrainingRecord::TRAINING_TYPE_FREE);
    });

    it('can create training record with paid training type', function () {
        Livewire::actingAs($this->admin)
            ->test(CreateTrainingRecord::class)
            ->fillForm([
                'employee_id' => $this->employee->id,
                'training_name' => 'Test Paid Training',
                'status' => TrainingRecord::STATUS_PLANNED,
                'training_type' => TrainingRecord::TRAINING_TYPE_PAID,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(TrainingRecord::where('training_name', 'Test Paid Training')->exists())->toBeTrue();
        $record = TrainingRecord::where('training_name', 'Test Paid Training')->first();
        expect($record->training_type)->toBe(TrainingRecord::TRAINING_TYPE_PAID);
    });

    it('defaults training_type to free', function () {
        Livewire::actingAs($this->admin)
            ->test(CreateTrainingRecord::class)
            ->assertFormSet(['training_type' => TrainingRecord::TRAINING_TYPE_FREE]);
    });
});

describe('TrainingRecordResource Table PGP Columns', function () {
    it('displays training_type column in table', function () {
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->create();

        Livewire::actingAs($this->admin)
            ->test(ListTrainingRecords::class)
            ->assertTableColumnExists('training_type');
    });

    it('displays pgp_points column in table', function () {
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create();

        Livewire::actingAs($this->admin)
            ->test(ListTrainingRecords::class)
            ->assertTableColumnExists('pgp_points');
    });

    it('displays approved_at column in table', function () {
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create();

        Livewire::actingAs($this->admin)
            ->test(ListTrainingRecords::class)
            ->assertTableColumnExists('approved_at');
    });

    it('can filter by training type', function () {
        $freeRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->create();

        $paidRecord = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->paidTraining()
            ->create();

        Livewire::actingAs($this->admin)
            ->test(ListTrainingRecords::class)
            ->filterTable('training_type', TrainingRecord::TRAINING_TYPE_FREE)
            ->assertCanSeeTableRecords([$freeRecord])
            ->assertCanNotSeeTableRecords([$paidRecord]);
    });

    it('can filter records with PGP points', function () {
        $withPgp = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create();

        $withoutPgp = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->create();

        Livewire::actingAs($this->admin)
            ->test(ListTrainingRecords::class)
            ->filterTable('with_pgp', true)
            ->assertCanSeeTableRecords([$withPgp])
            ->assertCanNotSeeTableRecords([$withoutPgp]);
    });
});
