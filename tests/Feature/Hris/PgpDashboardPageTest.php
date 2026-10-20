<?php

use App\Filament\Pages\PgpDashboardPage;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use App\Services\Hris\PgpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature: pgp-system, Task 7.4: PgpDashboardPage Feature Tests
 * Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5
 */

beforeEach(function () {
    // Create necessary roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    // Create user with employee
    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);

    // Get current quarter info
    $pgpService = app(PgpService::class);
    $this->quarter = $pgpService->getCurrentQuarter();
});

describe('PgpDashboardPage Rendering', function () {
    it('renders for authenticated employee', function () {
        Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class)
            ->assertSuccessful()
            ->assertSee('Homefinder Growth Point Dashboard');
    });

    it('displays quarter label correctly', function () {
        Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class)
            ->assertSee("Q{$this->quarter['quarter']} {$this->quarter['year']}");
    });

    it('displays target points as 150', function () {
        Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class)
            ->assertSee('150');
    });
});

describe('PgpDashboardPage Progress Display', function () {
    it('shows zero progress when no trainings approved', function () {
        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        expect($summary['currentPoints'])->toBe(0);
        expect($summary['progressPercentage'])->toBe(0.0);
        expect($summary['projectedReward'])->toBe(0);
    });

    it('shows correct progress percentage for partial completion', function () {
        // Create approved training with 35 PGP
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create([
                'approved_at' => Carbon::now(),
            ]);

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        expect($summary['currentPoints'])->toBe(35);
        // 35/150 * 100 = 23.33...
        expect($summary['progressPercentage'])->toBeGreaterThan(23.0);
        expect($summary['progressPercentage'])->toBeLessThan(24.0);
    });

    it('caps progress at 100% when exceeding target', function () {
        // Create multiple approved trainings totaling more than 150 PGP
        for ($i = 0; $i < 5; $i++) {
            TrainingRecord::factory()
                ->forEmployee($this->employee)
                ->freeTraining()
                ->approvedForPgp(35)
                ->create([
                    'approved_at' => Carbon::now(),
                ]);
        }

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        expect($summary['currentPoints'])->toBe(175);
        expect($summary['progressPercentage'])->toBe(100.0);
    });
});

describe('PgpDashboardPage Reward Display', function () {
    it('shows Rp0 reward when below target', function () {
        // Create training with 35 PGP (below 150 target)
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create([
                'approved_at' => Carbon::now(),
            ]);

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        expect($summary['projectedReward'])->toBe(0);
    });

    it('shows base reward when reaching exactly 150 PGP', function () {
        // Create trainings totaling exactly 150 PGP (4 free + 1 paid with 10 points won't work)
        // Let's use 4 free trainings (140) + 1 paid with custom points
        for ($i = 0; $i < 4; $i++) {
            TrainingRecord::factory()
                ->forEmployee($this->employee)
                ->freeTraining()
                ->approvedForPgp(35)
                ->create([
                    'approved_at' => Carbon::now(),
                ]);
        }
        // Add one more with 10 points to reach 150
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->paidTraining()
            ->create([
                'pgp_points' => 10,
                'approved_at' => Carbon::now(),
            ]);

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        expect($summary['currentPoints'])->toBe(150);
        expect($summary['projectedReward'])->toBe(150000);
    });

    it('shows incremental reward for points above target', function () {
        // Create 5 free trainings = 175 PGP
        for ($i = 0; $i < 5; $i++) {
            TrainingRecord::factory()
                ->forEmployee($this->employee)
                ->freeTraining()
                ->approvedForPgp(35)
                ->create([
                    'approved_at' => Carbon::now(),
                ]);
        }

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        // 175 PGP = 150000 base + floor((175-150)/35) * 35000 = 150000 + 0 = 150000
        // (25 extra points, no full 35-point block)
        expect($summary['currentPoints'])->toBe(175);
        expect($summary['projectedReward'])->toBe(150000);
    });

    it('shows correct reward with full incremental blocks', function () {
        // Create 6 free trainings = 210 PGP
        for ($i = 0; $i < 6; $i++) {
            TrainingRecord::factory()
                ->forEmployee($this->employee)
                ->freeTraining()
                ->approvedForPgp(35)
                ->create([
                    'approved_at' => Carbon::now(),
                ]);
        }

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        // 210 PGP = 150000 base + floor((210-150)/35) * 35000 = 150000 + 35000 = 185000
        expect($summary['currentPoints'])->toBe(210);
        expect($summary['projectedReward'])->toBe(185000);
    });

    it('formats reward as Indonesian Rupiah', function () {
        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $formatted = $component->instance()->formatRupiah(150000);
        expect($formatted)->toBe('Rp150.000');

        $formatted = $component->instance()->formatRupiah(185000);
        expect($formatted)->toBe('Rp185.000');
    });
});

describe('PgpDashboardPage Training List', function () {
    it('shows only current quarter trainings', function () {
        // Create training in current quarter
        $currentQuarterTraining = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create([
                'training_name' => 'Current Quarter Training',
                'approved_at' => Carbon::now(),
            ]);

        // Create training in previous quarter
        $previousQuarterDate = Carbon::now()->subMonths(4);
        $previousQuarterTraining = TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create([
                'training_name' => 'Previous Quarter Training',
                'approved_at' => $previousQuarterDate,
            ]);

        $component = Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class);

        $trainings = $component->instance()->getApprovedTrainings();

        expect($trainings->count())->toBe(1);
        expect($trainings->first()->training_name)->toBe('Current Quarter Training');
    });

    it('shows empty state when no trainings', function () {
        Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class)
            ->assertSee('No approved trainings this quarter');
    });

    it('displays training details in list', function () {
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->freeTraining()
            ->approvedForPgp(35)
            ->create([
                'training_name' => 'Laravel Advanced Course',
                'provider' => 'Laracasts',
                'approved_at' => Carbon::now(),
            ]);

        Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class)
            ->assertSee('Laravel Advanced Course')
            ->assertSee('Laracasts')
            ->assertSee('Free')
            ->assertSee('+35 PGP');
    });

    it('shows paid training type badge correctly', function () {
        TrainingRecord::factory()
            ->forEmployee($this->employee)
            ->paidTraining()
            ->create([
                'training_name' => 'AWS Certification',
                'pgp_points' => 20,
                'approved_at' => Carbon::now(),
            ]);

        Livewire::actingAs($this->user)
            ->test(PgpDashboardPage::class)
            ->assertSee('AWS Certification')
            ->assertSee('Paid')
            ->assertSee('+20 PGP');
    });
});

describe('PgpDashboardPage User Without Employee', function () {
    it('shows zero values for user without employee record', function () {
        // Create user without employee
        $userWithoutEmployee = User::factory()->create();
        $userWithoutEmployee->assignRole('super_admin');

        $component = Livewire::actingAs($userWithoutEmployee)
            ->test(PgpDashboardPage::class);

        $summary = $component->instance()->getPgpSummary();

        expect($summary['currentPoints'])->toBe(0);
        expect($summary['progressPercentage'])->toBe(0.0);
        expect($summary['projectedReward'])->toBe(0);
    });

    it('shows empty training list for user without employee record', function () {
        $userWithoutEmployee = User::factory()->create();
        $userWithoutEmployee->assignRole('super_admin');

        $component = Livewire::actingAs($userWithoutEmployee)
            ->test(PgpDashboardPage::class);

        $trainings = $component->instance()->getApprovedTrainings();

        expect($trainings->count())->toBe(0);
    });
});
