<?php

use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\Hris\Employee;
use App\Models\User;

describe('WeeklyAllowanceBatch Model Status', function () {
    it('has correct status constants', function () {
        expect(WeeklyAllowanceBatch::STATUS_DRAFT)->toBe('draft')
            ->and(WeeklyAllowanceBatch::STATUS_PENDING_HR)->toBe('pending_hr')
            ->and(WeeklyAllowanceBatch::STATUS_PENDING_FINANCE)->toBe('pending_finance')
            ->and(WeeklyAllowanceBatch::STATUS_PENDING_FINAL)->toBe('pending_final')
            ->and(WeeklyAllowanceBatch::STATUS_APPROVED)->toBe('approved')
            ->and(WeeklyAllowanceBatch::STATUS_PAID)->toBe('paid')
            ->and(WeeklyAllowanceBatch::STATUS_CANCELLED)->toBe('cancelled');
    });

    it('returns all statuses', function () {
        $statuses = WeeklyAllowanceBatch::getStatuses();

        expect($statuses)->toBeArray()
            ->and($statuses)->toContain('draft')
            ->and($statuses)->toContain('pending_hr')
            ->and($statuses)->toContain('pending_finance')
            ->and($statuses)->toContain('approved')
            ->and($statuses)->toContain('paid');
    });

    it('returns status labels', function () {
        $labels = WeeklyAllowanceBatch::getStatusLabels();

        expect($labels)->toBeArray()
            ->and($labels['draft'])->toBe('Draft')
            ->and($labels['approved'])->toBe('Approved')
            ->and($labels['paid'])->toBe('Paid');
    });

    it('returns status colors', function () {
        $colors = WeeklyAllowanceBatch::getStatusColors();

        expect($colors)->toBeArray()
            ->and($colors['draft'])->toBe('gray')
            ->and($colors['approved'])->toBe('success')
            ->and($colors['paid'])->toBe('success');
    });
});

describe('WeeklyAllowanceBatch Workflow Helpers', function () {
    it('can submit to HR from draft', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'draft']);

        expect($batch->canSubmitToHr())->toBeTrue()
            ->and($batch->isEditable())->toBeTrue();
    });

    it('can submit to finance from pending_hr', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'pending_hr']);

        expect($batch->canSubmitToFinance())->toBeTrue()
            ->and($batch->canSubmitToHr())->toBeFalse();
    });

    it('can approve by finance from pending_finance', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'pending_finance']);

        expect($batch->canApproveByFinance())->toBeTrue();
    });

    it('can approve final from pending_final', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'pending_final']);

        expect($batch->canApproveFinal())->toBeTrue();
    });

    it('can mark as paid from approved', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'approved']);

        expect($batch->canMarkAsPaid())->toBeTrue()
            ->and($batch->isLocked())->toBeTrue();
    });

    it('is locked when approved or paid', function () {
        $approved = new WeeklyAllowanceBatch(['status' => 'approved']);
        $paid = new WeeklyAllowanceBatch(['status' => 'paid']);
        $draft = new WeeklyAllowanceBatch(['status' => 'draft']);

        expect($approved->isLocked())->toBeTrue()
            ->and($paid->isLocked())->toBeTrue()
            ->and($draft->isLocked())->toBeFalse();
    });

    it('cannot cancel when paid', function () {
        $paid = new WeeklyAllowanceBatch(['status' => 'paid']);
        $draft = new WeeklyAllowanceBatch(['status' => 'draft']);

        expect($paid->canCancel())->toBeFalse()
            ->and($draft->canCancel())->toBeTrue();
    });
});

describe('WeeklyAllowanceBatch Accessors', function () {
    it('returns week label', function () {
        $batch = new WeeklyAllowanceBatch([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
        ]);

        expect($batch->week_label)->toBe('20 Jan - 24 Jan 2026');
    });

    it('returns status label', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'pending_finance']);

        expect($batch->status_label)->toBe('Pending Finance');
    });

    it('returns status color', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'approved']);

        expect($batch->status_color)->toBe('success');
    });
});
