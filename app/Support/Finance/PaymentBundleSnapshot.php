<?php

namespace App\Support\Finance;

use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;

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
