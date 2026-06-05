<?php

use App\Filament\Resources\ApplicantResource\Pages\ListApplicants;
use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\ApplicantScreeningResult;
use App\Models\User;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Database\Seeders\RecruitmentScreeningPermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $this->job = Ats::publicJob();
    Ats::createCriterion($this->job, 'must_have', 'Accounting', ['accounting']);
    $this->applicant = Ats::storedResumeApplicant($this->job);
});

it('shows screening score and recommendation columns to authorized HR', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting'));
    app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    $admin = tap(User::factory()->create())->assignRole('hr-admin');
    $this->actingAs($admin);

    Livewire::test(ListApplicants::class)
        ->assertTableColumnExists('screening_score')
        ->assertTableColumnExists('screening_recommendation')
        ->assertSee('Strong Match');
});

it('lets authorized HR queue screening from the applicant table', function () {
    Queue::fake();
    $staff = tap(User::factory()->create())->assignRole('hr-staff');
    $this->actingAs($staff);

    Livewire::test(ListApplicants::class)
        ->callTableAction('runScreening', $this->applicant);

    Queue::assertPushed(ScreenApplicantJob::class, 1);
});

it('does not let a viewer trigger Run Screening through a direct Livewire action call', function () {
    Queue::fake();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'view_any_applicant', 'guard_name' => 'web']));
    $this->actingAs($viewer);

    try {
        Livewire::test(ListApplicants::class)
            ->callTableAction('runScreening', $this->applicant);
    } catch (Throwable) {
        // Filament may reject hidden/unauthorized table actions by throwing; either way,
        // the important invariant is that no screening job is queued.
    }

    Queue::assertNotPushed(ScreenApplicantJob::class);
});

it('requires an override note from authorized HR', function () {
    $admin = tap(User::factory()->create())->assignRole('hr-admin');
    $this->actingAs($admin);

    Livewire::test(ListApplicants::class)
        ->callTableAction('overrideScreening', $this->applicant, [
            'recommendation' => ApplicantScreeningResult::REC_REVIEW,
            'note' => '',
        ])
        ->assertHasTableActionErrors(['note' => ['required']]);
});
