<?php

use App\Models\Hris\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('private');
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-staff', 'guard_name' => 'web']);

    $this->hr = tap(User::factory()->create())->assignRole('hr-admin');

    $this->applicant = Applicant::factory()->create([
        'resume_disk' => 'private',
        'resume_file_path' => 'recruitment/resumes/1/abc.pdf',
        'resume_original_filename' => 'jane-cv.pdf',
        'resume_mime_type' => 'application/pdf',
    ]);
    Storage::disk('private')->put($this->applicant->resume_file_path, '%PDF-1.4 content');
});

it('lets an HR user view the résumé inline as PDF', function () {
    $res = $this->actingAs($this->hr)
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/view");

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('inline');
});

it('lets an HR user download the résumé as an attachment', function () {
    $res = $this->actingAs($this->hr)
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/download");

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment');
    expect($res->headers->get('content-disposition'))->toContain('jane-cv.pdf');
});

it('returns 404 when the résumé file is missing on disk', function () {
    Storage::disk('private')->delete($this->applicant->resume_file_path);

    $this->actingAs($this->hr)
        ->get("/admin/recruitment/applicants/{$this->applicant->id}/resume/view")
        ->assertNotFound();
});
