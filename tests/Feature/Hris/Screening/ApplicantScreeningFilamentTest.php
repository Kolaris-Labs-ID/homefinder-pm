<?php

use App\Filament\Resources\ApplicantResource\Pages\ListApplicants;
use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Models\User;
use Database\Seeders\RecruitmentScreeningPermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $this->job = JobPosting::factory()->create(['hiring_company_id' => HiringCompany::factory()->create()->id]);
    $this->applicant = Applicant::factory()->forJobPosting($this->job)->create();
});

it('hides the runScreening action from a user who can view applicants but lacks screening permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'view_any_applicant', 'guard_name' => 'web']));
    $this->actingAs($viewer);

    Livewire::test(ListApplicants::class)
        ->assertTableActionHidden('runScreening', $this->applicant);
});

it('hides the override action from hr-staff (override is hr-admin only)', function () {
    $staff = tap(User::factory()->create())->assignRole('hr-staff');
    $this->actingAs($staff);

    Livewire::test(ListApplicants::class)
        ->assertTableActionHidden('overrideScreening', $this->applicant);
});

it('lets authorized hr-staff queue screening from the applicants table', function () {
    Queue::fake();
    $staff = tap(User::factory()->create())->assignRole('hr-staff');
    $this->actingAs($staff);

    Livewire::test(ListApplicants::class)
        ->callTableAction('runScreening', $this->applicant);

    Queue::assertPushed(ScreenApplicantJob::class, 1);
});

it('renders the applicants list with the screening column for hr-admin', function () {
    $admin = tap(User::factory()->create())->assignRole('hr-admin');
    $this->actingAs($admin);

    Livewire::test(ListApplicants::class)
        ->assertSuccessful()
        ->assertTableColumnExists('screening_recommendation');
});
