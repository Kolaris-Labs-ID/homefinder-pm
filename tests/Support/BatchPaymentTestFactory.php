<?php

namespace Tests\Support;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;

/**
 * Shared builders for the batch-payment test suite. Lives under the Tests\ PSR-4 namespace so it is
 * autoloaded for both single-file and full-suite runs (unlike Pest's per-file global helpers).
 */
class BatchPaymentTestFactory
{
    public static function employee(array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'bank_name' => 'BCA',
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'Ayu Finance',
        ], $overrides));
    }

    /**
     * Eligible weekly-allowance batch with one approved payable line. Pass $bankAccount = null to
     * simulate missing payable bank data (the adapter then blocks bundling).
     */
    public static function weeklyAllowance(Employee $employee, string $amount = '125000.00', ?string $bankAccount = '9876543210'): WeeklyAllowanceBatch
    {
        return self::weeklyAllowanceWithPayees([
            ['employee' => $employee, 'amount' => $amount, 'bank_account' => $bankAccount],
        ]);
    }

    /**
     * @param  array<int, array{employee: Employee, amount: string, bank_account?: ?string}>  $payees
     */
    public static function weeklyAllowanceWithPayees(array $payees): WeeklyAllowanceBatch
    {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-05-25',
            'week_end_date' => '2026-05-31',
            'payment_date' => '2026-06-02',
            'status' => WeeklyAllowanceBatch::STATUS_PAID,
            'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
            'total_employees' => count($payees),
            'total_amount' => self::sumPayees($payees),
        ]);

        foreach ($payees as $payee) {
            $account = array_key_exists('bank_account', $payee) ? $payee['bank_account'] : '9876543210';
            WeeklyAllowancePayment::create([
                'weekly_allowance_batch_id' => $batch->id,
                'employee_id' => $payee['employee']->id,
                'daily_rate' => $payee['amount'],
                'days_present' => 1,
                'total_amount' => $payee['amount'],
                'bank_name' => $account ? 'BCA' : null,
                'bank_account_number' => $account,
                'bank_account_holder' => $account ? ($payee['employee']->bank_account_holder ?? 'Ayu Finance') : null,
                'status' => WeeklyAllowancePayment::STATUS_APPROVED,
            ]);
        }

        return $batch;
    }

    public static function overtime(Employee $employee, string $amount = '75000.00'): OvertimePayBatch
    {
        $batch = OvertimePayBatch::create([
            'week_start_date' => '2026-05-25',
            'week_end_date' => '2026-05-31',
            'payment_date' => '2026-06-02',
            'status' => OvertimePayBatch::STATUS_PAID,
            'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
            'total_employees' => 1,
            'total_amount' => $amount,
        ]);

        OvertimePayment::create([
            'overtime_pay_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'total_sessions' => 1,
            'total_duration_minutes' => 60,
            'total_amount' => $amount,
            'bank_name' => 'BCA',
            'bank_account_number' => $employee->bank_account_number ?? '9876543210',
            'bank_account_holder' => $employee->bank_account_holder ?? 'Ayu Finance',
            'status' => OvertimePayment::STATUS_APPROVED,
        ]);

        return $batch;
    }

    public static function latePay(Employee $employee, string $amount = '50000.00'): LatePayCompensationBatch
    {
        $batch = LatePayCompensationBatch::create([
            'scheduled_pay_date' => '2026-05-30',
            'actual_pay_date' => '2026-06-02',
            'days_late' => 3,
            'rate_percent' => '1.00',
            'status' => LatePayCompensationBatch::STATUS_PAID,
            'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
            'total_employees' => 1,
            'total_amount' => $amount,
        ]);

        LatePayCompensationItem::create([
            'late_pay_compensation_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary_snapshot' => '5000000.00',
            'days_late' => 3,
            'rate_percent' => '1.00',
            'total_amount' => $amount,
            'bank_name' => 'BCA',
            'bank_account_number' => $employee->bank_account_number ?? '9876543210',
            'bank_account_holder' => $employee->bank_account_holder ?? 'Ayu Finance',
            'status' => LatePayCompensationItem::STATUS_APPROVED,
        ]);

        return $batch;
    }

    /** Eligible payrun batch — the payroll adapter still blocks it (Phase 9 guard). */
    public static function payrun(): PayrunBatch
    {
        return PayrunBatch::create([
            'period_start_date' => '2026-05-01',
            'period_end_date' => '2026-05-31',
            'pay_date' => '2026-06-02',
            'status' => PayrunBatch::STATUS_PAID,
            'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        ]);
    }

    /** A transparent-background PNG data URI accepted by the signature capture path. */
    public static function inkPng(): string
    {
        $img = imagecreatetruecolor(100, 50);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);
        $black = imagecolorallocate($img, 0, 0, 0);
        imageline($img, 5, 25, 95, 25, $black);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    /**
     * Drive a draft bundle all the way to authorized_for_payment: submit for signature then sign
     * all three slots (finance ×2 → executive). Signers must already hold the seeded signer roles.
     */
    public static function authorize(
        PaymentBundleService $bundles,
        PaymentBundleSignatureService $signatures,
        PaymentBundle $bundle,
        User $financeA,
        User $financeB,
        User $executive,
        User $creator,
    ): PaymentBundle {
        $bundle = $bundles->submitForSignature($bundle->fresh(), [
            PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $financeA->id,
            PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $financeB->id,
            PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $executive->id,
        ], $creator);

        foreach (PaymentBundleSignatureSlot::ordered() as $slot) {
            $sig = $bundle->fresh()->signatures()->where('signature_slot', $slot->value)->firstOrFail();
            $signatures->submitSignature($sig, $sig->assignedUser, self::inkPng());
        }

        return $bundle->fresh();
    }

    /**
     * @param  array<int, array{employee: Employee, amount: string, bank_account?: ?string}>  $payees
     */
    private static function sumPayees(array $payees): string
    {
        $total = '0';
        foreach ($payees as $payee) {
            $total = bcadd($total, $payee['amount'], 2);
        }

        return $total;
    }
}
