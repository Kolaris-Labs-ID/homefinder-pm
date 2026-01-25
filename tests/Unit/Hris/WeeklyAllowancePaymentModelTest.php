<?php

use App\Models\Hris\WeeklyAllowancePayment;

describe('WeeklyAllowancePayment Model Status', function () {
    it('has correct status constants', function () {
        expect(WeeklyAllowancePayment::STATUS_PENDING)->toBe('pending')
            ->and(WeeklyAllowancePayment::STATUS_APPROVED)->toBe('approved')
            ->and(WeeklyAllowancePayment::STATUS_EXCLUDED)->toBe('excluded')
            ->and(WeeklyAllowancePayment::STATUS_PAID)->toBe('paid');
    });

    it('returns all statuses', function () {
        $statuses = WeeklyAllowancePayment::getStatuses();

        expect($statuses)->toBeArray()
            ->and($statuses)->toContain('pending')
            ->and($statuses)->toContain('approved')
            ->and($statuses)->toContain('excluded')
            ->and($statuses)->toContain('paid');
    });

    it('returns status labels', function () {
        $labels = WeeklyAllowancePayment::getStatusLabels();

        expect($labels)->toBeArray()
            ->and($labels['pending'])->toBe('Pending')
            ->and($labels['approved'])->toBe('Approved')
            ->and($labels['paid'])->toBe('Paid');
    });
});

describe('WeeklyAllowancePayment Accessors', function () {
    it('returns formatted amount', function () {
        $payment = new WeeklyAllowancePayment(['total_amount' => 350000]);

        expect($payment->formatted_amount)->toBe('Rp 350.000');
    });

    it('returns formatted daily rate', function () {
        $payment = new WeeklyAllowancePayment(['daily_rate' => 70000]);

        expect($payment->formatted_daily_rate)->toBe('Rp 70.000');
    });

    it('returns days breakdown label', function () {
        $payment = new WeeklyAllowancePayment([
            'days_breakdown' => [
                'mon' => true,
                'tue' => true,
                'wed' => false,
                'thu' => true,
                'fri' => true,
            ],
        ]);

        expect($payment->days_breakdown_label)->toBe('Sen, Sel, Kam, Jum');
    });

    it('returns dash for empty days breakdown', function () {
        $payment = new WeeklyAllowancePayment(['days_breakdown' => null]);

        expect($payment->days_breakdown_label)->toBe('-');
    });

    it('returns status label', function () {
        $payment = new WeeklyAllowancePayment(['status' => 'approved']);

        expect($payment->status_label)->toBe('Approved');
    });
});

describe('WeeklyAllowancePayment Bank Info', function () {
    it('detects complete bank info', function () {
        $payment = new WeeklyAllowancePayment([
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Ari Rudiana',
        ]);

        expect($payment->hasBankInfo())->toBeTrue();
    });

    it('detects missing bank name', function () {
        $payment = new WeeklyAllowancePayment([
            'bank_name' => null,
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Ari Rudiana',
        ]);

        expect($payment->hasBankInfo())->toBeFalse();
    });

    it('detects missing account number', function () {
        $payment = new WeeklyAllowancePayment([
            'bank_name' => 'BCA',
            'bank_account_number' => '',
            'bank_account_holder' => 'Ari Rudiana',
        ]);

        expect($payment->hasBankInfo())->toBeFalse();
    });

    it('detects missing account holder', function () {
        $payment = new WeeklyAllowancePayment([
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => null,
        ]);

        expect($payment->hasBankInfo())->toBeFalse();
    });
});
