<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\Employee;
use App\Services\Hris\RecruitmentService;

beforeEach(function () {
    $this->service = new RecruitmentService;
});

it('hires an offered applicant and links the employee', function () {
    $applicant = Applicant::factory()->create(['status' => Applicant::STATUS_OFFERED]);
    $employee = Employee::factory()->create();

    $this->service->hireApplicant($applicant, $employee);

    $applicant->refresh();
    expect($applicant->status)->toBe(Applicant::STATUS_HIRED);
    expect($applicant->hired_employee_id)->toBe($employee->id);
});

it('cannot hire an applicant who is not offered', function () {
    $applicant = Applicant::factory()->create(['status' => Applicant::STATUS_INTERVIEWED]);
    $employee = Employee::factory()->create();

    $this->service->hireApplicant($applicant, $employee);
})->throws(Exception::class);

it('never produces a hired applicant without a linked employee', function () {
    // The only path to hired is hireApplicant(Employee); advanceApplicant must refuse hired.
    $applicant = Applicant::factory()->create(['status' => Applicant::STATUS_OFFERED]);

    try {
        $this->service->advanceApplicant($applicant, Applicant::STATUS_HIRED);
    } catch (\Throwable) {
        // expected
    }

    expect($applicant->fresh()->status)->not->toBe(Applicant::STATUS_HIRED);
    expect(Applicant::where('status', Applicant::STATUS_HIRED)->whereNull('hired_employee_id')->exists())->toBeFalse();
});
