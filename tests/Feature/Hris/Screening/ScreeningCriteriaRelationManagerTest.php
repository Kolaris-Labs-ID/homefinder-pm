<?php

use App\Filament\Resources\JobPostingResource\Pages\EditJobPosting;
use App\Filament\Resources\JobPostingResource\RelationManagers\ScreeningCriteriaRelationManager;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Models\User;
use Database\Seeders\RecruitmentScreeningPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $this->admin = tap(User::factory()->create())->assignRole('hr-admin');
    $this->actingAs($this->admin);
    $this->job = JobPosting::factory()->create(['hiring_company_id' => HiringCompany::factory()->create()->id]);
});

function criteriaRelationManager(JobPosting $job)
{
    return Livewire::test(ScreeningCriteriaRelationManager::class, [
        'ownerRecord' => $job,
        'pageClass' => EditJobPosting::class,
    ]);
}

it('renders the screening criteria relation manager for an authorized user', function () {
    criteriaRelationManager($this->job)->assertSuccessful();
});

it('creates a clean criterion and stamps created_by', function () {
    criteriaRelationManager($this->job)
        ->callTableAction('create', data: [
            'group' => 'must_have',
            'label' => 'PHP',
            'keywords' => ['php'],
            'weight' => 2,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertHasNoTableActionErrors();

    $criterion = $this->job->screeningCriteria()->where('label', 'PHP')->first();
    expect($criterion)->not->toBeNull()
        ->and($criterion->created_by)->toBe($this->admin->id);
});

it('rejects a sensitive label inline in the relation manager', function () {
    criteriaRelationManager($this->job)
        ->callTableAction('create', data: [
            'group' => 'must_have',
            'label' => 'agama',
            'weight' => 1,
        ])
        ->assertHasTableActionErrors(['label']);
});
