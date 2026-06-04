<?php

use App\Models\Hris\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('private');
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-staff', 'guard_name' => 'web']);

    $this->applicant = Applicant::factory()->create([
        'resume_disk' => 'private',
        'resume_file_path' => 'recruitment/resumes/1/abc.pdf',
    ]);
    Storage::disk('private')->put($this->applicant->resume_file_path, '%PDF-1.4 content');
});

it('blocks an unauthenticated (public) user', function () {
    $res = $this->withHeaders(['Accept' => 'application/json'])
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/view");

    $res->assertUnauthorized(); // 401
});

it('blocks an authenticated user without a recruitment role', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/view")
        ->assertForbidden(); // 403
});

it('blocks a download for an authenticated user without a recruitment role', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/download")
        ->assertForbidden();
});

it('allows hr-staff to view as well', function () {
    $staff = tap(User::factory()->create())->assignRole('hr-staff');

    $this->actingAs($staff)
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/view")
        ->assertOk();
});
