<?php

use App\Facades\Features;
use App\Features\Feature;
use App\Filament\Pages\CompanyFeatures;
use App\Filament\Resources\ApplicantResource;
use App\Filament\Resources\HiringCompanyResource;
use App\Filament\Resources\InterviewResource;
use App\Filament\Resources\JobPostingResource;
use App\Models\AppFeature;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['features.preset' => null]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
});

function setFeature(Feature $feature, bool $on): void
{
    Features::initialize([$feature->value => $on], 'test');
}

// --- Features endpoint -------------------------------------------------------

it('publishes the company flags for the mobile app without login', function () {
    setFeature(Feature::GoogleLogin, false);

    $this->getJson('/api/features')
        ->assertOk()
        ->assertExactJson(['data' => [
            'module.recruitment' => true,
            'auth.google_login' => false,
            'payroll.overtime_statutory_caps' => true,
        ]]);
});

// --- Recruitment -------------------------------------------------------------

it('serves the public job API when recruitment is on', function () {
    setFeature(Feature::Recruitment, true);

    $this->getJson('/api/public/v1/jobs')->assertOk();
});

it('hides the public job API when recruitment is off', function () {
    setFeature(Feature::Recruitment, false);

    $this->getJson('/api/public/v1/jobs')->assertNotFound();
    $this->postJson('/api/public/v1/jobs/any-slug/apply')->assertNotFound();
});

it('closes the recruitment admin pages when recruitment is off', function () {
    $this->actingAs($this->admin);

    $resources = [JobPostingResource::class, ApplicantResource::class, InterviewResource::class, HiringCompanyResource::class];

    foreach ($resources as $resource) {
        expect($resource::canAccess())->toBeTrue("{$resource} should be reachable with recruitment on");
    }

    setFeature(Feature::Recruitment, false);

    foreach ($resources as $resource) {
        expect($resource::canAccess())->toBeFalse("{$resource} should be closed with recruitment off")
            ->and($resource::canGloballySearch())->toBeFalse();
    }

    $this->get(JobPostingResource::getUrl('index'))->assertForbidden();
});

it('hides the résumé routes when recruitment is off', function () {
    setFeature(Feature::Recruitment, false);

    $this->actingAs($this->admin)
        ->get('/admin/recruitment/applicants/1/resume/view')
        ->assertNotFound();
});

// --- Google sign-in ----------------------------------------------------------

it('removes Google sign-in from the API and the web when it is off', function () {
    setFeature(Feature::GoogleLogin, false);

    $this->postJson('/api/auth/google', ['id_token' => 'x'])->assertNotFound();
    $this->get('/auth/google')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
});

it('shows the Google button on the login page only when Google sign-in is on', function () {
    $this->get('/admin/login')->assertOk()->assertSee('Continue with Google');

    setFeature(Feature::GoogleLogin, false);

    $this->get('/admin/login')->assertOk()->assertDontSee('Continue with Google');
});

// --- Company Features page ---------------------------------------------------

it('lets a super admin lock the flags not set yet', function () {
    setFeature(Feature::Recruitment, false);

    Livewire::actingAs($this->admin)
        ->test(CompanyFeatures::class)
        ->assertFormFieldExists('auth__google_login')
        ->assertFormFieldDoesNotExist('module__recruitment')
        ->fillForm([
            'auth__google_login' => false,
            'payroll__overtime_statutory_caps' => true,
        ])
        ->callAction('lock')
        ->assertHasNoErrors();

    Features::flush();
    expect(Features::pending())->toBe([])
        ->and(Features::active(Feature::GoogleLogin))->toBeFalse()
        ->and(Features::active(Feature::OvertimeStatutoryCaps))->toBeTrue()
        ->and(AppFeature::where('key', 'auth.google_login')->value('source'))->toBe('user:'.$this->admin->id)
        // The flag set before the page opened is untouched.
        ->and(Features::active(Feature::Recruitment))->toBeFalse();
});

it('shows locked flags read-only', function () {
    Features::initialize(array_fill_keys(array_map(fn ($f) => $f->value, Feature::cases()), true), 'preset:homefinder');

    Livewire::actingAs($this->admin)
        ->test(CompanyFeatures::class)
        ->assertActionHidden('lock')
        ->assertSee('the homefinder preset');
});

it('is only for super admins', function () {
    $this->actingAs(User::factory()->create());

    expect(CompanyFeatures::canAccess())->toBeFalse();
});
