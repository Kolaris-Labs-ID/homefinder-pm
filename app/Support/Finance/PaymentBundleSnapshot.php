<?php

namespace App\Support\Finance;

use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;

/**
 * Single source of truth for the frozen per-item source snapshot and its deterministic hash.
 *
 * PaymentBundleService freezes these at assembly time (Phase 3); PaymentBundlePaymentService
 * rebuilds them from the live source at payment time (Phase 10) to detect drift. Both MUST use
 * the SAME builders/hash so a byte-identical live source yields a byte-identical hash. Changing a
 * key name, order, or value cast here changes every stored source_hash — do not.
 */
final class PaymentBundleSnapshot
{
    public static function forPaymentRequest(PaymentRequest $pr): array
    {
        return [
            'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            'source_id' => $pr->id,
            'payment_request_id' => $pr->id,
            'payment_request_number' => $pr->payment_request_number,
            'payee_type' => $pr->payee_type,
            'payee_name' => $pr->payee_name,
            'bank_name' => $pr->bank_name,
            'bank_account_last4' => $pr->bank_account_last4,
            'bank_account_number' => $pr->bank_account_number,
            'amount' => (string) $pr->amount,
            'currency' => $pr->currency,
            'description' => $pr->description,
            'withholding_required' => $pr->withholding_required,
            'withholding_amount' => $pr->withholding_amount !== null ? (string) $pr->withholding_amount : null,
            'approval_snapshot_hash' => $pr->approval_snapshot_hash,
        ];
    }

    public static function forWeeklyAllowanceBatch(WeeklyAllowanceBatch $wab): array
    {
        $snapshot = self::batchSnapshot(
            sourceType: PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            sourceId: (string) $wab->id,
            sourceNumber: 'WAB-'.$wab->id,
            sourceStatus: $wab->status,
            totalAmount: $wab->total_amount,
            summary: [
                'label' => 'Weekly Allowance',
                'week_label' => $wab->week_label,
                'week_start_date' => optional($wab->week_start_date)->toDateString(),
                'week_end_date' => optional($wab->week_end_date)->toDateString(),
            ],
            lines: $wab->payments()
                ->with('employee')
                ->whereIn('status', [
                    WeeklyAllowancePayment::STATUS_PENDING,
                    WeeklyAllowancePayment::STATUS_APPROVED,
                    WeeklyAllowancePayment::STATUS_PAID,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn (WeeklyAllowancePayment $payment) => [
                    'payee_id' => (string) $payment->employee_id,
                    'payee_name' => $payment->bank_account_holder ?: ($payment->employee?->user?->name ?? 'Employee #'.$payment->employee_id),
                    'bank_name' => $payment->bank_name,
                    'bank_account_number' => $payment->bank_account_number,
                    'amount' => $payment->total_amount,
                ])
                ->all(),
        );

        return $snapshot;
    }

    public static function forOvertimePayBatch(OvertimePayBatch $batch): array
    {
        return self::batchSnapshot(
            sourceType: PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH,
            sourceId: (string) $batch->id,
            sourceNumber: 'OT-'.$batch->id,
            sourceStatus: $batch->status,
            totalAmount: $batch->total_amount,
            summary: [
                'label' => 'Overtime Pay',
                'week_label' => $batch->week_label,
                'week_start_date' => optional($batch->week_start_date)->toDateString(),
                'week_end_date' => optional($batch->week_end_date)->toDateString(),
            ],
            lines: $batch->payments()
                ->with('employee')
                ->whereIn('status', [
                    OvertimePayment::STATUS_PENDING,
                    OvertimePayment::STATUS_APPROVED,
                    OvertimePayment::STATUS_PAID,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn (OvertimePayment $payment) => [
                    'payee_id' => (string) $payment->employee_id,
                    'payee_name' => $payment->bank_account_holder ?: ($payment->employee?->user?->name ?? 'Employee #'.$payment->employee_id),
                    'bank_name' => $payment->bank_name,
                    'bank_account_number' => $payment->bank_account_number,
                    'amount' => $payment->total_amount,
                ])
                ->all(),
        );
    }

    public static function forLatePayCompensationBatch(LatePayCompensationBatch $batch): array
    {
        return self::batchSnapshot(
            sourceType: PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH,
            sourceId: (string) $batch->id,
            sourceNumber: 'LPC-'.$batch->id,
            sourceStatus: $batch->status,
            totalAmount: $batch->total_amount,
            summary: [
                'label' => 'Late Pay Compensation',
                'period_label' => $batch->period_label,
                'scheduled_pay_date' => optional($batch->scheduled_pay_date)->toDateString(),
                'actual_pay_date' => optional($batch->actual_pay_date)->toDateString(),
                'days_late' => $batch->days_late,
                'rate_percent' => self::money($batch->rate_percent),
            ],
            lines: $batch->items()
                ->with('employee')
                ->whereIn('status', [
                    LatePayCompensationItem::STATUS_PENDING,
                    LatePayCompensationItem::STATUS_APPROVED,
                    LatePayCompensationItem::STATUS_PAID,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn (LatePayCompensationItem $item) => [
                    'payee_id' => (string) $item->employee_id,
                    'payee_name' => $item->bank_account_holder ?: ($item->employee?->user?->name ?? 'Employee #'.$item->employee_id),
                    'bank_name' => $item->bank_name,
                    'bank_account_number' => $item->bank_account_number,
                    'amount' => $item->total_amount,
                ])
                ->all(),
        );
    }

    private static function batchSnapshot(
        string $sourceType,
        string $sourceId,
        string $sourceNumber,
        string $sourceStatus,
        mixed $totalAmount,
        array $summary,
        array $lines,
    ): array {
        $payees = [];
        $protectedPayees = [];

        foreach ($lines as $line) {
            $account = (string) ($line['bank_account_number'] ?? '');
            $payeeId = (string) $line['payee_id'];

            $payees[] = [
                'payee_id' => $payeeId,
                'payee_name' => (string) $line['payee_name'],
                'bank_name' => $line['bank_name'],
                'bank_account_last4' => self::last4($account),
                'bank_account_encrypted_ref' => 'payee:'.$payeeId,
                'amount' => self::money($line['amount']),
                'currency' => 'IDR',
            ];

            $protectedPayees[] = [
                'payee_id' => $payeeId,
                'bank_account_number' => $account,
            ];
        }

        $snapshot = [
            'snapshot_version' => 1,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_number' => $sourceNumber,
            'source_status' => $sourceStatus,
            'currency' => 'IDR',
            'total_amount' => self::money($totalAmount),
            'payable_count' => count($payees),
            'summary' => $summary,
            'payees' => $payees,
            'protected_payees' => $protectedPayees,
        ];

        $snapshot['source_hash'] = self::hash($snapshot);

        return $snapshot;
    }

    public static function title(array $snapshot): string
    {
        $label = $snapshot['summary']['label'] ?? match ($snapshot['source_type'] ?? null) {
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH => 'Weekly Allowance',
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH => 'Overtime Pay',
            PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH => 'Late Pay Compensation',
            PaymentBundleItem::SOURCE_PAYRUN_BATCH => 'Payroll',
            default => 'Batch Payment',
        };

        return $label.' — '.($snapshot['source_number'] ?? $snapshot['source_id'] ?? '');
    }

    public static function firstPayeeName(array $snapshot): string
    {
        if (! empty($snapshot['payees'])) {
            return count($snapshot['payees']) === 1
                ? $snapshot['payees'][0]['payee_name']
                : self::title($snapshot);
        }

        return self::title($snapshot);
    }

    public static function firstPayeeBankName(array $snapshot): ?string
    {
        return $snapshot['payees'][0]['bank_name'] ?? null;
    }

    public static function firstPayeeLast4(array $snapshot): ?string
    {
        return $snapshot['payees'][0]['bank_account_last4'] ?? null;
    }

    public static function isVersionedBatchSnapshot(array $snapshot): bool
    {
        return isset($snapshot['snapshot_version'], $snapshot['payees'], $snapshot['protected_payees']);
    }

    public static function money(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    private static function last4(string $account): string
    {
        return substr(preg_replace('/\D+/', '', $account), -4) ?: '';
    }

    public static function legacyWeeklyAllowanceSnapshot(WeeklyAllowanceBatch $wab): array
    {
        return [
            'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            'source_id' => $wab->id,
            'payee_name' => 'Weekly Allowance — '.$wab->week_label,
            'amount' => (string) $wab->total_amount,
            'currency' => 'IDR',
            'week_start_date' => optional($wab->week_start_date)->toDateString(),
            'week_end_date' => optional($wab->week_end_date)->toDateString(),
            'total_employees' => $wab->total_employees,
            'description' => 'Weekly allowance batch payout',
        ];
    }

    /**
     * Deterministic sha256 over the plaintext business fields (recursive key-sort + canonical JSON),
     * so drift between an authorized item and its live source is detectable.
     */
    public static function hash(array $snapshot): string
    {
        unset($snapshot['source_hash']);
        self::ksortRecursive($snapshot);

        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function ksortRecursive(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
        unset($value);

        ksort($data);
    }
}
