<?php

use App\Models\Hris\Applicant;
use App\Services\Hris\RecruitmentService;

beforeEach(function () {
    $this->service = new RecruitmentService;
});

describe('forward pipeline transitions', function () {
    it('advances applied to screening', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_APPLIED]);
        $this->service->advanceApplicant($a, Applicant::STATUS_SCREENING);
        expect($a->fresh()->status)->toBe(Applicant::STATUS_SCREENING);
    });

    it('advances screening to shortlisted', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_SCREENING]);
        $this->service->advanceApplicant($a, Applicant::STATUS_SHORTLISTED);
        expect($a->fresh()->status)->toBe(Applicant::STATUS_SHORTLISTED);
    });

    it('advances shortlisted to interview_scheduled', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_SHORTLISTED]);
        $this->service->advanceApplicant($a, Applicant::STATUS_INTERVIEW_SCHEDULED);
        expect($a->fresh()->status)->toBe(Applicant::STATUS_INTERVIEW_SCHEDULED);
    });

    it('advances interview_scheduled to interviewed', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_INTERVIEW_SCHEDULED]);
        $this->service->advanceApplicant($a, Applicant::STATUS_INTERVIEWED);
        expect($a->fresh()->status)->toBe(Applicant::STATUS_INTERVIEWED);
    });

    it('advances interviewed to offered', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_INTERVIEWED]);
        $this->service->advanceApplicant($a, Applicant::STATUS_OFFERED);
        expect($a->fresh()->status)->toBe(Applicant::STATUS_OFFERED);
    });
});

describe('illegal transitions', function () {
    it('cannot skip stages', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_APPLIED]);
        $this->service->advanceApplicant($a, Applicant::STATUS_SHORTLISTED);
    })->throws(Exception::class);

    it('cannot reach hired via advanceApplicant', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_OFFERED]);
        $this->service->advanceApplicant($a, Applicant::STATUS_HIRED);
    })->throws(Exception::class);

    it('offered has no further advance target', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_OFFERED]);
        expect($a->getNextStatus())->toBeNull();
        expect($a->canAdvance())->toBeFalse();
    });
});

describe('withdraw', function () {
    it('withdraws an active applicant', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_SCREENING]);
        $this->service->withdrawApplicant($a);
        expect($a->fresh()->status)->toBe(Applicant::STATUS_WITHDRAWN);
    });

    it('cannot advance a withdrawn applicant', function () {
        $a = Applicant::factory()->create(['status' => Applicant::STATUS_WITHDRAWN]);
        $this->service->advanceApplicant($a, Applicant::STATUS_SCREENING);
    })->throws(Exception::class);

    it('cannot withdraw a hired applicant', function () {
        $a = Applicant::factory()->hired()->create();
        $this->service->withdrawApplicant($a);
    })->throws(Exception::class);
});

describe('reject guards', function () {
    it('cannot reject a hired applicant', function () {
        $a = Applicant::factory()->hired()->create();
        $this->service->rejectApplicant($a, 'nope');
    })->throws(Exception::class);

    it('cannot advance a rejected applicant', function () {
        $a = Applicant::factory()->rejected()->create();
        $this->service->advanceApplicant($a, Applicant::STATUS_SCREENING);
    })->throws(Exception::class);
});
