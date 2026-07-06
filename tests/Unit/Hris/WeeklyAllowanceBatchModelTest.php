<?php

use App\Models\Hris\WeeklyAllowanceBatch;

describe('WeeklyAllowanceBatch Model Status', function () {
    it('has the unified status constants', function () {
        expect(WeeklyAllowanceBatch::STATUS_DRAFT)->toBe('draft')
            ->and(WeeklyAllowanceBatch::STATUS_CALCULATED)->toBe('calculated')
            ->and(WeeklyAllowanceBatch::STATUS_PENDING_FINANCE)->toBe('pending_finance')
            ->and(WeeklyAllowanceBatch::STATUS_REJECTED)->toBe('rejected')
            ->and(WeeklyAllowanceBatch::STATUS_PAID)->toBe('paid');
    });

    it('returns all statuses', function () {
        $statuses = WeeklyAllowanceBatch::getStatuses();

        expect($statuses)->toBeArray()
            ->and($statuses)->toContain('draft')
            ->and($statuses)->toContain('calculated')
            ->and($statuses)->toContain('pending_finance')
            ->and($statuses)->toContain('rejected')
            ->and($statuses)->toContain('paid');
    });

    it('returns status labels', function () {
        $labels = WeeklyAllowanceBatch::getStatusLabels();

        expect($labels)->toBeArray()
            ->and($labels['draft'])->toBe('Draft')
            ->and($labels['pending_finance'])->toBe('Pending Finance')
            ->and($labels['paid'])->toBe('Paid');
    });

    it('returns status colors', function () {
        $colors = WeeklyAllowanceBatch::getStatusColors();

        expect($colors)->toBeArray()
            ->and($colors['draft'])->toBe('gray')
            ->and($colors['pending_finance'])->toBe('warning')
            ->and($colors['paid'])->toBe('success');
    });
});

describe('WeeklyAllowanceBatch Workflow Helpers (unified finance approval)', function () {
    it('can submit for approval from calculated', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'calculated']);

        expect($batch->canSubmitForApproval())->toBeTrue();
    });

    it('can be approved by finance from pending_finance', function () {
        $batch = new WeeklyAllowanceBatch(['status' => 'pending_finance']);

        expect($batch->canBeApprovedByFinance())->toBeTrue()
            ->and($batch->canSubmitForApproval())->toBeFalse();
    });

    it('reports paid only in paid status', function () {
        expect((new WeeklyAllowanceBatch(['status' => 'paid']))->isPaid())->toBeTrue()
            ->and((new WeeklyAllowanceBatch(['status' => 'pending_finance']))->isPaid())->toBeFalse();
    });

    it('is editable in draft or rejected', function () {
        expect((new WeeklyAllowanceBatch(['status' => 'draft']))->isEditable())->toBeTrue()
            ->and((new WeeklyAllowanceBatch(['status' => 'rejected']))->isEditable())->toBeTrue()
            ->and((new WeeklyAllowanceBatch(['status' => 'pending_finance']))->isEditable())->toBeFalse();
    });

    it('is locked when pending_finance or paid', function () {
        expect((new WeeklyAllowanceBatch(['status' => 'pending_finance']))->isLocked())->toBeTrue()
            ->and((new WeeklyAllowanceBatch(['status' => 'paid']))->isLocked())->toBeTrue()
            ->and((new WeeklyAllowanceBatch(['status' => 'draft']))->isLocked())->toBeFalse();
    });

    // Full lifecycle matrix — isLocked() is the single source of truth for
    // mutability, so every status must be asserted explicitly rather than
    // leaving new statuses to silently fall through as "not locked".
    it('covers every status in the isLocked lifecycle matrix', function (string $status, bool $editable, bool $locked) {
        $batch = new WeeklyAllowanceBatch(['status' => $status]);

        expect($batch->isEditable())->toBe($editable)
            ->and($batch->isLocked())->toBe($locked);
    })->with([
        'draft — editable, not locked' => ['draft', true, false],
        'calculated — awaiting HR submission, not row-editable but not locked (batch-level recalculation stays available)' => ['calculated', false, false],
        'rejected — editable, not locked' => ['rejected', true, false],
        'pending_finance — submitted to finance, locked' => ['pending_finance', false, true],
        'eligible_for_bundle — queued for payment bundling, locked' => ['eligible_for_bundle', false, true],
        'in_payment_bundle — inside an active payment bundle, locked' => ['in_payment_bundle', false, true],
        'paid — locked' => ['paid', false, true],
    ]);
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
        $batch = new WeeklyAllowanceBatch(['status' => 'paid']);

        expect($batch->status_color)->toBe('success');
    });
});
